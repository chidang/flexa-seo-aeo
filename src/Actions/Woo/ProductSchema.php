<?php

declare(strict_types=1);

namespace Flexa\SeoAeo\Actions\Woo;

use Flexa\SeoAeo\Services\Aeo\Schema as SchemaService;
use Flexa\SeoAeo\Services\Aeo\WooProduct;
use Flexa\SeoAeo\Support\Settings;
use Flexa\SeoAeo\Support\SingletonTrait;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce → AEO bridge. This is the only place that touches WooCommerce
 * symbols: it extracts a plain data array from the current `WC_Product` and
 * hands it to the pure {@see WooProduct} builder, then injects the resulting
 * Product node into the JSON-LD graph through the `schema_nodes` seam — so
 * `Services\Aeo\Schema` never learns about commerce. It also drops the generic
 * Article node the core schema builds for the product page, since a Product is
 * the correct primary entity there.
 *
 * Gated by `aeo.schema` + `aeo.commerce`; only booted when WooCommerce is
 * active (see {@see \Flexa\SeoAeo\Plugin::boot()}).
 */
final class ProductSchema {
	use SingletonTrait;

	public function register(): void {
		if ( is_admin() ) {
			return;
		}

		add_filter( 'flexa_seo_aeo/aeo/schema_nodes', [ $this, 'inject' ] );
		add_filter( 'woocommerce_structured_data_product', [ $this, 'maybe_suppress' ] );
	}

	/**
	 * Stand WooCommerce's own Product node down when ours is going out.
	 *
	 * Both use `{permalink}#product` as the `@id`, so leaving both in the page
	 * gives one identifier two different descriptions of the same thing, and a
	 * consumer merging the graph by `@id` gets whichever it read last. Ours
	 * carries the brand, GTIN and review counts, so ours is the one to keep.
	 *
	 * Asked of the same cached node list the page prints: if either toggle is
	 * off, or a `flexa_seo_aeo/aeo/schema_nodes` filter drops our node, the
	 * store keeps the markup it had before this plugin was installed.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function maybe_suppress( array $data ): array {
		if ( ! (bool) Settings::get_aeo( 'schema' ) || ! (bool) Settings::get_aeo( 'commerce' ) ) {
			return $data;
		}

		return SchemaService::instance()->emits( 'Product' ) ? [] : $data;
	}

	/**
	 * @param list<array<string, mixed>> $nodes
	 * @return list<array<string, mixed>>
	 */
	public function inject( array $nodes ): array {
		if ( ! (bool) Settings::get_aeo( 'schema' ) || ! (bool) Settings::get_aeo( 'commerce' ) ) {
			return $nodes;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) {
			return $nodes;
		}

		$product = wc_get_product();
		if ( ! $product instanceof WC_Product ) {
			return $nodes;
		}

		$node = WooProduct::instance()->node( $this->extract( $product ) );
		if ( [] === $node ) {
			return $nodes;
		}

		$url     = (string) $product->get_permalink();
		$nodes   = $this->drop_article( $nodes, $url );
		$nodes[] = $node;

		return array_values( $nodes );
	}

	/**
	 * Remove the auto-generated Article node for this product URL so the graph
	 * carries a single, correct primary entity (the Product).
	 *
	 * @param list<array<string, mixed>> $nodes
	 * @return list<array<string, mixed>>
	 */
	private function drop_article( array $nodes, string $url ): array {
		$article_id = $url . '#article';

		return array_values(
			array_filter(
				$nodes,
				static fn( array $node ): bool => ( $node['@id'] ?? '' ) !== $article_id
			)
		);
	}

	/**
	 * Pull everything the builder needs out of the WooCommerce product. All the
	 * version-variant getters (native GTIN, the `product_brand` taxonomy) are
	 * probed defensively so this works across WooCommerce releases and with or
	 * without a brands add-on.
	 *
	 * @return array<string, mixed>
	 */
	private function extract( WC_Product $product ): array {
		$variable = $product->is_type( 'variable' );

		$data = [
			'name'         => $product->get_name(),
			'description'  => $this->description( $product ),
			'url'          => $product->get_permalink(),
			'sku'          => $product->get_sku(),
			'gtin'         => $this->gtin( $product ),
			'brand'        => $this->brand( $product ),
			'image'        => $this->image( $product ),
			'variable'     => $variable,
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'availability' => $this->availability( $product ),
			'rating_value' => (float) $product->get_average_rating(),
			'rating_count' => (int) $product->get_rating_count(),
			'review_count' => (int) $product->get_review_count(),
		];

		if ( $variable && method_exists( $product, 'get_variation_price' ) ) {
			$data['low_price']  = $this->price( $product->get_variation_price( 'min', true ) );
			$data['high_price'] = $this->price( $product->get_variation_price( 'max', true ) );
		} else {
			$data['price']             = $this->price( $this->display_price( $product ) );
			$data['price_valid_until'] = $this->price_valid_until( $product );
		}

		return $data;
	}

	/**
	 * Short description first (it's the marketing summary), falling back to the
	 * full description, always reduced to a single plain-text line.
	 */
	private function description( WC_Product $product ): string {
		$text = (string) $product->get_short_description();
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			$text = (string) $product->get_description();
		}

		return trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
	}

	/**
	 * Native WooCommerce GTIN (9.2+) when present, else the common `_gtin`
	 * postmeta some add-ons write.
	 */
	private function gtin( WC_Product $product ): string {
		// The stubs pin a single WooCommerce version so phpstan sees this getter
		// as always present, but it only exists from WC 9.2 — the guard keeps
		// older stores from fataling.
		// @phpstan-ignore function.alreadyNarrowedType
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$gtin = (string) $product->get_global_unique_id();
			if ( '' !== $gtin ) {
				return $gtin;
			}
		}

		$meta = $product->get_meta( '_gtin' );

		return is_scalar( $meta ) ? (string) $meta : '';
	}

	/**
	 * Brand name from WooCommerce's native `product_brand` taxonomy (9.6+) or a
	 * brands add-on registering the same taxonomy. First term wins.
	 */
	private function brand( WC_Product $product ): string {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return '';
		}

		$terms = get_the_terms( $product->get_id(), 'product_brand' );
		if ( ! is_array( $terms ) || [] === $terms ) {
			return '';
		}

		$first = $terms[0];

		return $first instanceof \WP_Term ? $first->name : '';
	}

	private function image( WC_Product $product ): string {
		$image_id = $product->get_image_id();
		if ( '' === (string) $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( (int) $image_id, 'full' );

		return is_string( $url ) ? $url : '';
	}

	private function availability( WC_Product $product ): string {
		if ( $product->is_on_backorder() ) {
			return 'BackOrder';
		}

		return $product->is_in_stock() ? 'InStock' : 'OutOfStock';
	}

	/**
	 * Tax-aware display price, respecting the shop's catalogue display setting so
	 * the structured price matches what the customer actually sees.
	 */
	private function display_price( WC_Product $product ): mixed {
		if ( function_exists( 'wc_get_price_to_display' ) ) {
			return wc_get_price_to_display( $product );
		}

		return $product->get_price();
	}

	private function price_valid_until( WC_Product $product ): string {
		if ( ! $product->is_on_sale() ) {
			return '';
		}

		$date = $product->get_date_on_sale_to();
		if ( null === $date || ! method_exists( $date, 'date' ) ) {
			return '';
		}

		return (string) $date->date( 'Y-m-d' );
	}

	/**
	 * Normalise a WooCommerce price to a fixed-decimal string using the shop's
	 * configured precision — schema.org wants a plain decimal, not a formatted
	 * currency string.
	 */
	private function price( mixed $value ): string {
		if ( '' === (string) $value || ! is_numeric( $value ) ) {
			return '';
		}

		if ( function_exists( 'wc_format_decimal' ) && function_exists( 'wc_get_price_decimals' ) ) {
			return (string) wc_format_decimal( $value, wc_get_price_decimals() );
		}

		return (string) $value;
	}
}
