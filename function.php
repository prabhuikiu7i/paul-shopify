<?php

class  ShopifyProduct{
	private $api_url = 'https://mr-motorcycles-nz.myshopify.com/admin/api/2024-10/';
	
	private $shopify_token = file_get_contents('shopify_access_token.txt');

function getProductBySKU($product_sku){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'graphql.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'{
		  "query": "query { productVariants(first: 1, query: \\"sku:'.$product_sku.'\\") { edges { node { id sku title product { id title } } } } }"
		}
		',
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: '. $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode ($response,true);
	}
	
	
	
	function insertProduct($newProductData){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'products.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>json_encode($newProductData),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: '. $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode ($response,true);
	}
	
	
	function saveImage($productId,$images){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'products/' . $productId . '/images.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => json_encode(["image" => $images]),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: ' . $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode($response,true);
	}
	
	
	function saveVariant($newVariantData,$productId){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'products/'. $productId .'/variants.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => json_encode($newVariantData),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: ' . $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode($response, true);
	}
	
	
	function addVariantStock($variantStock){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'inventory_levels/set.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => json_encode($variantStock),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: ' . $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode($response,true);
	}
	
	
	
	
	function saveProductPrice($productPrice,$variant_id){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'variants/'. $variant_id .'.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'PUT',
		  CURLOPT_POSTFIELDS => json_encode($productPrice),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: ' . $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode($response,true);
	}
	
	
	function getProductByID($productId){
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'products/'. $productId .'.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: '. $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode($response,true);
	}
	
	
	function updatePriceIfProductExist($variant_id,$productPrice){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'variants/'. $variant_id .'.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'PUT',
		  CURLOPT_POSTFIELDS => json_encode($productPrice),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: '. $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode($response,true);
	}
	
	function updateStockIfProductExist($variantStock){
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->api_url . 'inventory_levels/set.json',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS => json_encode($variantStock),
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'X-Shopify-Access-Token: '. $this->shopify_token,
		  ),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		
		return json_decode($response,true);

	}
}
