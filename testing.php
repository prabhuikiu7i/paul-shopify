<?php
require "function.php";

$ShopifyProduct = new ShopifyProduct();

$currentDateTime = new DateTime("now", new DateTimeZone("Asia/Kolkata"));

$data = file_get_contents('crownkiwi-api.jsp.json');
$array_data = json_decode($data,true);

$countFile = 'count.txt';
$countFilePath = __DIR__ . DIRECTORY_SEPARATOR . $countFile;

if (file_exists($countFile)) {
    $latestAuditLog = file_get_contents($countFile);
    $startCount = $latestAuditLog ? (int)$latestAuditLog : 0;
} else {
    $startCount = 0;
}

$batchSize = 1;
$totalRecords = count($array_data);

if ($totalRecords == 0) {
	$logEntries = [
        [
            "status" => "No records available.",
            "timestamp" => $currentDateTime->format("Y-m-d H:i:s"),
        ]
    ];

	file_put_contents('logs.txt', json_encode($logEntries, JSON_PRETTY_PRINT));
    exit;
}

$endCount = $startCount + $batchSize;
$batchToProcess = array_slice($array_data, $startCount, $batchSize);

	$counter = 0;
	$productPriceResponse = 0;
	foreach($batchToProcess as $product){
		$product_title = $product['ItemName'];
		$product_sku = $product['SKU'];
		$product_qty = $product['Qty'];

		$sku_parts = explode('-', $product_sku);
		$processed_sku = '';
		$last_part = '';
		
		if (count($sku_parts) === 4) {
			$processed_sku = implode('-', array_slice($sku_parts, 0, 3));
			$last_part = $sku_parts[3];
		} elseif (count($sku_parts) === 5) {
			$processed_sku = implode('-', array_slice($sku_parts, 0, 4));
			 $last_part = $sku_parts[4];
		}

			$imageSrcArray = $product['Image Src']; 
			foreach($imageSrcArray as $imageURL){
				$fileName = basename(parse_url($imageURL, PHP_URL_PATH));

				$imageContent = file_get_contents($imageURL);
					
				if ($imageContent !== false) {
					$base64Image = base64_encode($imageContent);
				} else {
					echo "Failed to retrieve the image.";
				}
			}

		$getProductBySKU = $ShopifyProduct->getProductBySKU($product_sku);

		if (isset($getProductBySKU['data']['productVariants']['edges']) && count($getProductBySKU['data']['productVariants']['edges']) > 0) {
			
			echo "Product exists: " . $getProductBySKU['data']['productVariants']['edges'][0]['node']['product']['title'] . "<br><br>";
			
			$product_id = $getProductBySKU['data']['productVariants']['edges'][0]['node']['product']['id'];
			$parts = explode('/', $product_id);
			$productId = end($parts);

			$getProductByID = $ShopifyProduct->getProductByID($productId);
				
			if (isset($getProductByID['product']['id']) && $getProductByID['product']['variants']){
				$productID = $getProductByID['product']['id'];
				$variants = $getProductByID['product']['variants'];
				$locationId = '61176086681';
				 
				foreach ($variants as $variant) {
					if ($product_sku == $variant['sku']) {
						$inventoryItemId = $variant['inventory_item_id'];

						$variantStock = [
							"location_id" => $locationId,
							"inventory_item_id" => $inventoryItemId,
							"available" => $product_qty 
						];

						$updateStockResponse = $ShopifyProduct->updateStockIfProductExist($variantStock);

						$logEntries[] = [
							"status" => "Stock Updated Successfully.",
							"timestamp" => $currentDateTime->format("Y-m-d H:i:s"),
							"sku" => $product_sku,
							"inventory_item_id" => $inventoryItemId,
							"response" => $updateStockResponse
						];

						break;
					}
				}
			}
		} else {
			echo "Product does not exist: $product_title <br>";
			
			if($processed_sku && strpos($product_sku, $processed_sku) !== false) {
				echo "It has variants: $product_title ($product_sku)<br>";
				
				$newProductData = [
						"product" => [
							"title" => $product_title,
							"body_html" => $product['Body (HTML)'],
							"product_type" => $product['Cat'],
							"status" => "draft", 
							"images" => [
								[
									"attachment" => $base64Image,
									"filename" => $fileName
								]
							]
						]
					];

					$createProductResponse = $ShopifyProduct->insertProduct($newProductData);

					echo "<pre>";
					print_r($createProductResponse);
					echo "</pre>";
					
					if (isset($createProductResponse['product']['id'])) {
						$productId = $createProductResponse['product']['id'];
						$newVariantData = [
							"variant" => [
								"sku" => $product_sku,	
								"title" => $last_part,
								"price" => $product['rrp'],
								"inventory_quantity" => $product['Qty'],
								"product_id" => $productId,
								"option1" => $last_part,
								"cost" => $product['Cost']
							]
						];
				
						$createVariantResponse = $ShopifyProduct->saveVariant($newVariantData,$productId);
						if (isset($createVariantResponse['variant']['inventory_item_id'])){
							
							$inventory_item_id = $createVariantResponse['variant']['inventory_item_id'];
							$locationid = '61176086681';
							
							$variantStock = [
								"location_id" => $locationId,
								"inventory_item_id" => $inventory_item_id,
								"available" => $product_qty
							];
							$addVariantStockResponse = $ShopifyProduct->addVariantStock($variantStock);
							
							$logEntries[] = [
								"status" => "Variant Created",
								"timestamp" => $currentDateTime->format("Y-m-d H:i:s"),
								"title" => $product_title,
								"sku" => $product_sku,
								"product_id" => $productId,
								"variant_response" => $createVariantResponse,
								"stock_update_response" => $addVariantStockResponse
							];
						}
					}
			} else {
				echo "single product: $product_title ($product_sku)<br>";
				
					$newProductData = [
						"product" => [
							"title" => $product_title,
							"body_html" => $product['Body (HTML)'],
							"product_type" => $product['Cat'],
							"status" => "draft", 
							"images" => [
								[
									"attachment" => $base64Image,
									"filename" => $fileName
								]
							]
						]
					];


					$createProductResponse = $ShopifyProduct->insertProduct($newProductData);

					echo "<pre>";
					print_r($createProductResponse);
					echo "</pre>";
					
					if(isset($createProductResponse['product']['id'])) {
						$productId = $createProductResponse['product']['id'];
						
						if (isset($createProductResponse['product']['variants']) && count($createProductResponse['product']['variants']) > 0) {
							foreach ($createProductResponse['product']['variants'] as $variant) {
								$variant_id = $variant['id']; 
								
								$productPrice = [
									"variant" => [
										"product_id" => $productId,
										"price" => $product['rrp'], 
										"cost" => $product['Cost'], 
										"sku" => $product['SKU']   
									]
								];

								$productPriceResponse = $ShopifyProduct->saveProductPrice($productPrice, $variant_id);   
								echo "<pre>";
								print_r($productPriceResponse);
								echo "</pre>";
							}
						} else {
							echo "No variants found!";
						}
					} else {
						echo "Product ID not found!";
					}

					$logEntries[] = [
						"status" => "Single Product Created Successfully.",
						"timestamp" => $currentDateTime->format("Y-m-d H:i:s"),
						"sku" => $product_sku,
						"product_id" => $productId,
						"response" => $createProductResponse,
						"Product Price" => $productPriceResponse,
					];
			}
		}
}

file_put_contents('logs.txt', json_encode($logEntries, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

$nextStartCount = $startCount + $batchSize;
file_put_contents($countFilePath, $nextStartCount);

if ($nextStartCount >= $totalRecords) {
    echo 'All records processed.<br>';
}
