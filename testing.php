<?php
require "function.php";

$ShopifyProduct = new ShopifyProduct();
$currentDateTime = new DateTime("now", new DateTimeZone("Asia/Kolkata"));

$data = file_get_contents('crownkiwi-api.jsp.json');
$array_data = json_decode($data,true);

$countFile = 'count.txt';

if (file_exists($countFile)) {
    $latestAuditLog = file_get_contents($countFile);
    $startCount = $latestAuditLog ? (int)$latestAuditLog : 0;
} else {
    $startCount = 0;
}

$batchSize = 15;
$totalRecords = count($array_data);

if ($totalRecords == 0) {
	$logEntries =
				"======================= No records available ! =======================\n" .
					$currentDateTime->format("Y-m-d H:i:s") . "\n" .
					json_encode([
						"status" => "No records available."
					]) .
				"\n=====================================================================\n";

	file_put_contents('logs.txt',$logEntries, FILE_APPEND);
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

		if (empty($product_sku)) {
			$logEntries =
						"======================= Missing SKU ! =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Missing SKU !",
								"title" => $product_title,
								"message" => "No SKU provided for this product.",
							]) .
						"\n=====================================================================\n";
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

			$imageSrcArray = $product['Image Src'];
			$images = [];
			foreach ($imageSrcArray as $imageSrc) {
				$fileName = basename($imageSrc);  
				$imageData = file_get_contents($imageSrc); 
				
				if ($imageData !== false) {
					$base64Encoded = base64_encode($imageData); 
					$images[] = [
						"filename" => $fileName,
						"attachment" => $base64Encoded
					];
				} else {
					$logEntries =
							"======================= Image could not be fetched ! =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Image could not be fetched.",
								"message" => "Image could not be fetched: " . $imageSrc
							]) .
							"\n=====================================================================\n";
				}
			} 
	
		$getProductBySKU = $ShopifyProduct->getProductBySKU($product_sku);

		if (isset($getProductBySKU['data']['productVariants']['edges']) && count($getProductBySKU['data']['productVariants']['edges']) > 0) {
			
			echo "Product exists: " . $getProductBySKU['data']['productVariants']['edges'][0]['node']['product']['title'] . "<br><br>";
			
			$product_id = $getProductBySKU['data']['productVariants']['edges'][0]['node']['product']['id'];
			$parts = explode('/', $product_id);
			$productId = end($parts);

			if (empty($productId)) {
				$logEntries =
						"======================= Product ID Missing ! =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Product ID Missing !",
								"product_title" => $getProductBySKU['data']['productVariants']['edges'][0]['node']['product']['title'],
								"message" => "Failed to extract product ID from Shopify response."
							]) .
						"\n=====================================================================\n";
			}

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

						$logEntries =
							"======================= Stock Update Log =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Stock Updated Successfully.",
								"sku" => $product_sku,
								"inventory_item_id" => $inventoryItemId,
								"response" => $updateStockResponse
							]) .
							"\n=====================================================================\n";
						break;
					}
				}
			}else {
				$logEntries =
							"======================= Product or Variants Missing =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Product or Variants Missing",
								"sku" => $product_sku,
								"message" => "Either product ID or variants are missing.",
							]) .
							"\n=====================================================================\n";
			}
		} else {
			echo "Product does not exist: $product_title <br>";
			$count_variants = substr_count($product_sku, $processed_sku);
			if($processed_sku && strpos($product_sku, $processed_sku)  !== false && $count_variants > 1) {
				echo "It has variants: $product_title ($product_sku)<br>";
				
				$newProductData = [
						"product" => [
							"title" => $product_title,
							"body_html" => $product['Body (HTML)'],
							"product_type" => $product['Cat'],
							"status" => "draft",
							"images" => $images
						]
					];
					
					$createProductResponse = $ShopifyProduct->insertProduct($newProductData);

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
							$locationId = '61176086681';
							
							$variantStock = [
								"location_id" => $locationId,
								"inventory_item_id" => $inventory_item_id,
								"available" => $product_qty
							];
							$addVariantStockResponse = $ShopifyProduct->addVariantStock($variantStock);
							
							$logEntries =
							"======================= Variant Created Successfully =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Variant Created Successfully.",
								"title" => $product_title,
								"sku" => $product_sku,
								"product_id" => $productId,
								"variant_response" => $createVariantResponse,
								"stock_update_response" => $addVariantStockResponse
							]) .
							"\n=====================================================================\n";

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
							"images" => $images
						]
					];
					$createProductResponse = $ShopifyProduct->insertProduct($newProductData);

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
							}
						} else {
							$logEntries =
							"======================= No Variants Found ! =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "No Variants Found !",
								"sku" => $product_sku,
								"product_id" => $productId,
								"message" => "Product created but no variants were returned.",
							]) .
							"\n=====================================================================\n";
						}
					} else {
						$logEntries =
							"======================= Product ID not found ! =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Product ID not found !",
								"sku" => $product_sku,
								"title" => $product_title,
								"response" => $createProductResponse,
								"message" => "Product creation failed or Product ID is not returned.",
							]) .
							"\n=====================================================================\n";
					}

					$logEntries =
							"======================= Single Product Created Successfully. =======================\n" .
							$currentDateTime->format("Y-m-d H:i:s") . "\n" .
							json_encode([
								"status" => "Single Product Created Successfully.",
								"sku" => $product_sku,
								"product_id" => $productId,
								"response" => $createProductResponse,
								"Product Price" => $productPriceResponse,
							]) .
							"\n=====================================================================\n";
			}
		}
		file_put_contents('logs.txt', $logEntries, FILE_APPEND);
}

$nextStartCount = $startCount + $batchSize;
file_put_contents($countFile, $nextStartCount);

if ($nextStartCount >= $totalRecords) {
    echo 'All records processed.<br>';
}
