# Hyvä Themes - GraphQL ViewModel module

[![Hyvä Themes](https://repository-images.githubusercontent.com/300568807/f00eb480-55b1-11eb-93d2-074c3edd2d07)](https://hyva.io/)

## hyva-themes/magento2-graphql-view-model

![Supported Magento Versions][ico-compatibility]

This module adds a GraphQL ViewModel to allow GraphQL queries and mutations to be customized before they are rendered in the output.

Compatible with Magento 2.3.4 and higher.

## What does it do?

It provides:
 - `\Hyva\GraphqlViewModel\ViewModel\GraphqlViewModel`, to be accessed via the view model registry (or injected via Layout XML).
 - `\Hyva\GraphqlViewModel\Model\GraphqlQueryEditor` which can be used to add fields and arguments to GraphQL queries.
 - The event `hyva_graphql_render_before_` + query identifier
   Event observers receive the query string and can manipulate it with the `GraphqlQueryEditor`

## Usage

In `.phtml` templates, to make queries customizable, wrap them with the `GraphqlViewModel::query()` method:
```php
<?= $gqlViewModel->query("product_list_query", "
products(filter: {} pageSize: 20) {
  items {
    {$type}_products {
        sku
        id
        small_image {
          url
        }
    }
  }
}", ['type' => $type])
?>
```
The first argument is the event name suffix.  
The second argument is the query or mutation as a string.  
The third argument is optional and - if specified - will be merged into the event arguments.

In the above example the full event name would be `hyva_graphql_render_before_product_list_query`

To manipulate a query in an event observer, the GraphqlQueryEditor can be used:
```php

public function execute(Observer $event)
{
    $gqlEditor = new GraphqlQueryEditor(); // or use dependency injection
    
    $queryString = $event->getData('gql_container')->getData('query');
    $linkType  = $event->getData('type');
    $path  = ['products', 'items', ($linkType ? "{$linkType}_products" : 'products'), 'small_image'];
    
    // add a single field to a result object
    $queryString = $gqlEditor->addFieldIn($queryString, $path, 'url_webp');
    
    // add multiple fields to a result object
    $queryString = $gqlEditor->addFieldIn($queryString, ['products', 'items', 'products', 'image'], 'label url_webp');
    
    // add a query argument
    $queryString = $gqlEditor->addArgumentIn($queryString, ['products', 'filter', 'name'], 'match', 'Tank');
    $queryString = $gqlEditor->addArgumentIn($queryString, ['products'], 'pageSize', 2);
    
    // set updated query back on container
    $event->getData('gql_container')->setData('query', $queryString);
}
```

The result of the example method call
```php
$gqlEditor->addFieldIn($queryString, ['products', 'items', 'products', 'small_image'], 'label url_webp')
```
is that in the query the fields at the specified path are set:

```graphql
products {
  items {
    products {
      small_image {
        label
        url_webp
      }
    }
  }
}
```

Both the `addFieldIn` and the `addArgumentIn` methods are idempotent, so if the specified values already exist in the
query string they are not changed.

The `addArgumentIn` method can be used to add new arguments to queries or mutations, or to overwrite values of existing arguments.

For more examples including inline fragments please have a look at the `\Hyva\GraphqlViewModel\Model\GraphqlQueryEditorTest` class.

## Installation
  
1. Install via composer
   ```
   composer config repositories.hyva-themes/magento2-graphql-view-model git git@github.com:hyva-themes/magento2-graphql-view-model.git
   composer require hyva-themes/magento2-graphql-view-model
   ```
2. Enable module
   ```
   bin/magento module:enable Hyva_GraphqlViewModel
   ```

## Configuration
  
No configuration needed.

## License

The BSD-3-Clause License. Please see [License File](LICENSE.txt) for more information.

[ico-compatibility]: https://img.shields.io/badge/magento-%202.3%20|%202.4-brightgreen.svg?logo=magento&longCache=true&style=flat-square
