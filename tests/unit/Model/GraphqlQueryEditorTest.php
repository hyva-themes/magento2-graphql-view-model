<?php declare(strict_types=1);
/**
 * Hyvä Themes - https://hyva.io
 * Copyright © Hyvä Themes. All rights reserved.
 * See LICENSE.md for license details
 */

namespace Hyva\GraphqlViewModel\Model;

use GraphQL\Language\Printer;
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

        $ast = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($query));

        $sut = new GraphqlQueryEditor();

        // new field in existing query object
        $sut->setFieldIn($ast, ['products', 'items', 'small_image'], 'url_webp');

        // multiple fields in new query object
        $sut->setFieldIn($ast, ['products', 'items', 'image'], 'url label url_webp');

        // existing field, idempotent
        $sut->setFieldIn($ast, ['products'], 'total_count');

        $this->assertSame($expected, Printer::doPrint($ast));
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
        $ast = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($query));

        $sut = new GraphqlQueryEditor();

        $sut->setArgumentIn($ast, ['products', 'filter', 'name'], 'match', 'Tank');
        $sut->setArgumentIn($ast, ['products'], 'pageSize', 2);

        $this->assertSame($expected, Printer::doPrint($ast));
    }
}
