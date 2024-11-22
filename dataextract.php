<?php

include "function.php";

$ShopifyProduct = new ShopifyProduct();

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

		if (count($sku_parts) === 4) {
			$processed_sku = implode('-', array_slice($sku_parts, 0, 3));
			$last_part = $sku_parts[3];
		} elseif (count($sku_parts) === 5) {
			$processed_sku = implode('-', array_slice($sku_parts, 0, 4));
			 $last_part = $sku_parts[4];
		}
		
	
		$imageSrcArray = $product['Image Src']; 
		$images = [];
		foreach ($imageSrcArray as $src) {
			$images = [
				"image" => [
					"src" => $src
				]
			];
		}
		
		$getProductBySKU = $ShopifyProduct->getProductBySKU($product_sku);

		if (isset($getProductBySKU['data']['productVariants']['edges']) && count($getProductBySKU['data']['productVariants']['edges']) > 0) {
			
			echo "Product exists: " . $getProductBySKU['data']['productVariants']['edges'][0]['node']['product']['title'] . "<br>";
			
			$product_id = $getProductBySKU['data']['productVariants']['edges'][0]['node']['product']['id'];
			$parts = explode('/', $product_id);
			$productId = end($parts);

			$getProductByID = $ShopifyProduct->getProductByID($productId);
				
			if (isset($getProductByID['product']['id']) && $getProductByID['product']['variants']){
				$productID = $getProductByID['product']['id'];
				$variants = $getProductByID['product']['variants'];
				$locationId = '61176086681';
				
					foreach ($variants as $variant) {
						$variant_id = $variant['id'];
						$variant_price = $variant['price'];
						$variant_title = $variant['title'];
						$variant_inventory_item_id = $variant['inventory_item_id'];
						
						
							$productPrice = [
									"variant" => [
										"product_id" => $productID,
										"price" => $product['rrp'],  
								]
							];
						
						$updatePriceResponse = $ShopifyProduct->updatePriceIfProductExist($variant_id,$productPrice);
						echo "<pre>";
						print_r($updatePriceResponse);
						echo "</pre>";
						echo "<hr>";
						
						
						$variantStock = [
								"location_id" => $locationId,
								"inventory_item_id" => $variant_inventory_item_id,
								"available" => $product_qty
						];
						
						$updateStockResponse = $ShopifyProduct->updateStockIfProductExist($variantStock);
						echo "<pre>";
						print_r($updateStockResponse);
						echo "</pre>";
						echo "<hr>";
					}
			}
			
			
		} else {
			
			echo "Product does not exist: $product_title <br>";
			
			if (strpos($product_sku, $processed_sku) !== false) {
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
						
						$saveImageResponse = $ShopifyProduct->saveImage($productId, $images);
						
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
						
						$saveImageResponse = $ShopifyProduct->saveImage($productId, $images);
						echo "<pre>";
						print_r($saveImageResponse);
						echo "</pre>";
						echo "<hr>";
						
						
						if (isset($createProductResponse['product']['variants'][0]['id'])) {
							$variant_id = $createProductResponse['product']['variants'][0]['id'];
							
							$productPrice = [
								"variant" => [
									"product_id" => $productId,
									"price" => $product['rrp'],
									"cost" => $product['Cost'],
									"sku" => $product['SKU']
								]
							];

							$productPriceResponse = $ShopifyProduct->saveProductPrice($productPrice,$variant_id);   
							echo "<pre>";
							print_r($productPriceResponse);
							echo "<pre>";
						} else {
							echo "Variant ID not found!";
						}
					} else {
						echo "Product ID not found!";
					}
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
