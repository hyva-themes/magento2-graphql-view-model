<?php declare(strict_types=1);
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See LICENSE.md for license details
 */

namespace Hyva\GraphqlViewModel\Model;

use PHPUnit\Framework\TestCase;

class GraphqlQueryEditorTest extends TestCase
{
    public function queryPrefixDataProvider(): array
    {
        return [
            'no prefix'   => ['', ''],
            'only query'  => ['query ', ''],
            'named query' => ['query ExampleQuery ', 'query ExampleQuery '],
        ];
    }

    public function mutationPrefixDataProvider(): array
    {
        return [
            'only mutation'  => ['mutation ', 'mutation '],
            'named mutation' => ['mutation ExampleMutation ', 'mutation ExampleMutation '],
        ];
    }

    /**
     * @dataProvider queryPrefixDataProvider
     */
    public function testSetsGivenFieldsOnQuery(string $inputPrefix, string $expectedPrefix): void
    {
        $query = $inputPrefix . '{
  products(filter: {name: {match: "Tank"}}) {
    total_count
    items {
      name
      small_image {
        url
        label
      }
    }
  }
}
';

        $expected = $expectedPrefix . '{
  products(filter: { name: { match: "Tank" } }) {
    total_count
    items {
      name
      small_image {
        url
        label
        url_webp
      }
      image {
        url
        label
        url_webp
      }
    }
  }
}
';
        $sut      = new GraphqlQueryEditor();

        // new field in existing query object
        $query = $sut->addFieldIn($query, ['products', 'items', 'small_image'], 'url_webp');

        // multiple fields in new query object
        $query = $sut->addFieldIn($query, ['products', 'items', 'image'], 'url label url_webp');

        // existing field, idempotent
        $query = $sut->addFieldIn($query, ['products'], 'total_count');

        $this->assertSame($expected, $query);
    }

    /**
     * @dataProvider queryPrefixDataProvider
     */
    public function testSetsGivenArgumentsOnQuery(string $inputPrefix, string $expectedPrefix): void
    {
        $query = $inputPrefix . '{
  products {
    total_count
    items {
      name
      small_image {
        url
        label
      }
    }
  }
}
';

        $expected = $expectedPrefix . '{
  products(filter: { name: { match: "Tank" } }, pageSize: 2) {
    total_count
    items {
      name
      small_image {
        url
        label
      }
    }
  }
}
';
        $sut   = new GraphqlQueryEditor();

        $query = $sut->addArgumentIn($query, ['products', 'filter', 'name'], 'match', 'Tank');

        $query = $sut->addArgumentIn($query, ['products'], 'pageSize', 2);

        $this->assertSame($expected, $query);
    }

    /**
     * @dataProvider queryPrefixDataProvider
     */
    public function testHandlesFieldsInInlineFragments(string $inputPrefix, string $expectedPrefix): void
    {
        $query = $inputPrefix . '{
  cart(cart_id: "%cartId") {
    items {
      quantity
      ... on SimpleCartItem {
        customizable_options {
          label
          values {
            label
            value
            price {
              value
              type
            }
          }
        }
      }
      ... on BundleCartItem {
        bundle_options {
          id
          label
          values {
            quantity
            label
          }
        }
        customizable_options {
          label
          values {
            label
            value
            price {
              value
              type
            }
          }
        }
      }
    }
  }
}';

        $expected = $expectedPrefix . '{
  cart(cart_id: "%cartId") {
    items {
      quantity
      ... on SimpleCartItem {
        customizable_options {
          label
          values {
            label
            value
            price {
              value
              type
            }
            foo
          }
        }
        id
      }
      ... on BundleCartItem {
        bundle_options {
          id
          label
          values {
            quantity
            label
          }
        }
        customizable_options {
          label
          values {
            label
            value
            price {
              value
              type
            }
            bar
          }
        }
      }
      ... on NewFragment {
        customizable_options {
          values {
            baz
          }
        }
      }
    }
  }
}';

        $sut = new GraphqlQueryEditor();

        $pathInFirstFragment = ['cart', 'items', '... on SimpleCartItem', 'customizable_options', 'values'];
        $query               = $sut->addFieldIn($query, $pathInFirstFragment, 'foo');

        $anotherPathInFirstFragment = ['cart', 'items', '... on SimpleCartItem'];
        $query               = $sut->addFieldIn($query, $anotherPathInFirstFragment, 'id');

        $pathInSecondFragment = ['cart', 'items', '... on BundleCartItem', 'customizable_options', 'values'];
        $query                = $sut->addFieldIn($query, $pathInSecondFragment, 'bar');

        $pathInNewFragment = ['cart', 'items', '... on NewFragment', 'customizable_options', 'values'];
        $query             = $sut->addFieldIn($query, $pathInNewFragment, 'baz');

        $this->assertSame($expected, trim($query));
    }

    /**
     * @dataProvider mutationPrefixDataProvider
     */
    public function testAddsArgumentsAndFieldsToMutations(string $inputPrefix, string $expectedPrefix): void
    {
        $query    = $inputPrefix . '{
  applyCouponToCart (
    input: {
      cart_id: "${this.cartId}",
      coupon_code: "${couponCode}",
    }
  )
  {
    cart {
      items {
        prices {
          row_total {
            value
          }
        }
        product_type
        ... on SimpleCartItem {
          customizable_options {
            values {
              price {
                value
              }
            }
          }
        }
      }
    }
  }
}';
        $expected = $expectedPrefix . '{
  applyCouponToCart(
    input: { cart_id: "${this.cartId}", coupon_code: "${couponCode}", special: true }
  ) {
    cart {
      items {
        prices {
          row_total {
            value
            currency
          }
        }
        product_type
        ... on SimpleCartItem {
          customizable_options {
            values {
              price {
                value
              }
              foo
            }
          }
        }
      }
    }
  }
}';

        $sut = new GraphqlQueryEditor();

        $query = $sut->addArgumentIn($query, ['applyCouponToCart', 'input'], 'special', true);
        $query = $sut->addFieldIn($query, ['applyCouponToCart', 'cart', 'items', 'prices', 'row_total'], 'currency');

        $path  = ['applyCouponToCart', 'cart', 'items', '... on SimpleCartItem', 'customizable_options', 'values'];
        $query = $sut->addFieldIn($query, $path, 'foo');

        $this->assertSame($expected, trim($query));

    }

    public function testAddPriceRangetoUpdateCartItemsQuery(): void
    {
        $query = 'mutation updateCartItemQtyMutation($cartId: String!, $itemId: Int, $qty: Float) {
  updateCartItems(input: {cart_id: $cartId, cart_items: [{cart_item_id: $itemId, quantity: $qty}]}) {
    cart {
      items {
        id
        errors
        product_type
        product {
          id
          name
        }
      }
    }
  }
}';
        $expected = 'mutation updateCartItemQtyMutation($cartId: String!, $itemId: Int, $qty: Float) {
  updateCartItems(
    input: { cart_id: $cartId, cart_items: [{ cart_item_id: $itemId, quantity: $qty }] }
  ) {
    cart {
      items {
        id
        errors
        product_type
        product {
          id
          name
          price_range {
            minimum_price {
              regular_price {
                value
                currency
              }
            }
          }
        }
      }
    }
  }
}';

        $sut = new GraphqlQueryEditor();

        $path = ['updateCartItems', 'cart', 'items', 'product', 'price_range', 'minimum_price', 'regular_price'];
        $query = $sut->addFieldIn($query, $path, 'value currency');

        $this->assertSame($expected, trim($query));
    }
}
