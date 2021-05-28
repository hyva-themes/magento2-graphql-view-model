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
    public function testSetsGivenFieldsOnQuery(): void
    {
        $query = '{
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

        $expected = '{
  products(filter: {name: {match: "Tank"}}) {
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
        $sut = new GraphqlQueryEditor();

        // new field in existing query object
        $query = $sut->addFieldIn($query, ['products', 'items', 'small_image'], 'url_webp');

        // multiple fields in new query object
        $query = $sut->addFieldIn($query, ['products', 'items', 'image'], 'url label url_webp');

        // existing field, idempotent
        $query = $sut->addFieldIn($query, ['products'], 'total_count');

        $this->assertSame($expected, $query);
    }

    public function testSetsGivenArgumentsOnQuery()
    {
        $query = '{
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

        $expected = '{
  products(filter: {name: {match: "Tank"}}, pageSize: 2) {
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
        $sut = new GraphqlQueryEditor();

        $query = $sut->addArgumentIn($query, ['products', 'filter', 'name'], 'match', 'Tank');

        $query = $sut->addArgumentIn($query, ['products'], 'pageSize', 2);

        $this->assertSame($expected, $query);
    }
}
