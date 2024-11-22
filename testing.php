<?php
include "function.php";

$ShopifyProduct = new ShopifyProduct();

$currentDateTime = new DateTime("now", new DateTimeZone("Asia/Kolkata"));

$data = file_get_contents('crownkiwi-api.jsp.json');
$array_data = json_decode($data,true);


$countFile = 'count.txt';
$latestAuditLog = file_get_contents($countFile);
$startCount = $latestAuditLog ? (int)$latestAuditLog : 0;

$processedDataFile = 'processed_data.json';
$processedData = [];

if (file_exists($processedDataFile)) {
    $processedData = json_decode(file_get_contents($processedDataFile), true);
    if (!is_array($processedData)) {
        $processedData = [];
    }
}

$batchSize = 5;
$totalRecords = count($array_data);

if ($totalRecords == 0) {
    echo "No records available.";
    exit;
}

$endCount = $startCount + $batchSize;
$batchToProcess = array_slice($array_data, $startCount, $batchSize);

	$counter = 0;
	foreach($batchToProcess as $product){
		$product_title = $product['ItemName'];
		$product_sku = $product['SKU'];
		$product_qty = $product['Qty'];
		
		$isProcessed = false;
		foreach ($processedData as $processedItem) {
			if ($processedItem['sku'] === $product_sku) {
				$isProcessed = true;
				break;
			}
		}

		if ($isProcessed) {
			echo "Skipping already processed product: $product_title ($product_sku)<br>";
			continue;
		}
		
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
		
		/* $imageSrcArray = $product['Image Src']; 
		$images = [];

		foreach ($imageSrcArray as $index => $src) {
			$images[] = ["src" => $src]; 
		}
		$data = json_encode(["image" => $images]);

		echo $data . "<br>"; */

	
		$imageSrcArray = "https://shopmrmoto.co.nz/cdn/shop/files/50005-00032.jpg"; 

		$images = [
			"src" => $imageSrcArray
		]; 
		
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
						echo "<br>";
						echo "Stocks Updated Successfully!!";
						$logData = "======================= Stock Updated =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							"SKU: $product_sku\n" .
							"Inventory Item ID: $inventoryItemId\n" .
							"Response: " . json_encode($updateStockResponse) . "\n" .
							"=====================================================================\n";
						file_put_contents("stock_updated.txt", $logData, FILE_APPEND);

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
						]
					];
					$createProductResponse = $ShopifyProduct->insertProduct($newProductData);
					
					if (isset($createProductResponse['product']['id'])) {
						
						$productId = $createProductResponse['product']['id'];
						
						$saveImageResponse = $ShopifyProduct->saveImage($productId,$images);
						
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
							
							$logData = "======================= Variant Created =======================\n" .
								$currentDateTime->format("Y-m-d H:i:s") . "\n" .
								"Title: $product_title\n" .
								"SKU: $product_sku\n" .
								"Product ID: $productId\n" .
								"Variant Response: " . json_encode($createVariantResponse) . "\n" .
								"Stock Update Response: " . json_encode($addVariantStockResponse) . "\n" .
								"=====================================================================\n";
							file_put_contents("variant_created.txt", $logData, FILE_APPEND);
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
						]
					];
					$createProductResponse = $ShopifyProduct->insertProduct($newProductData);
					if(isset($createProductResponse['product']['id'])) {
						$productId = $createProductResponse['product']['id'];
						
						$saveImageResponse = $ShopifyProduct->saveImage($productId,$images);
						echo "<hr>";
						echo "image respone !";
						echo "<pre>";
						print_r($saveImageResponse);
						echo "</pre>";
						echo "<hr>";
						
						
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
					$logData = "======================= Single Product Created =======================\n" .
						$currentDateTime->format("Y-m-d H:i:s") . "\n" .
						"Title: $product_title\n" .
						"SKU: $product_sku\n" .
						"Product ID: " . (isset($productId) ? $productId : 'Not Found') . "\n" .
						"Create Product Response: " . json_encode($createProductResponse, JSON_PRETTY_PRINT) . "\n" .
						"Product Price Response: " . json_encode($productPriceResponse, JSON_PRETTY_PRINT) . "\n" .
						"=====================================================================\n";
					file_put_contents("single_product_created.txt", $logData, FILE_APPEND);
			}
		}
    $processedData[] = [
        'title' => $product_title,
        'sku' => $product_sku,
        'body (HTML)' => $product['Body (HTML)'],
        'quantity' => $product_qty,
        'price' => $product['rrp'],
        'cost' => $product['Cost'],
        'category' => $product['Cat'],
        'images' => $product['Image Src']
    ];
}

file_put_contents($processedDataFile, json_encode($processedData, JSON_PRETTY_PRINT));

$nextStartCount = $startCount + $batchSize;
file_put_contents($countFile, $nextStartCount);

if ($nextStartCount >= $totalRecords) {
    echo 'All records processed.<br>';
}

