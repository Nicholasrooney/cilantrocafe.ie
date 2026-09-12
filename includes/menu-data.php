<?php
/*
 * Menu items and prices. To change a price, edit the 'price' value.
 * Allergen codes go in 'allergens' as an array, e.g. ['G', 'D'].
 */

$salsaNote = 'Salsa: verde (mild), roja (medium) or negra (spicy).';

$allergenKey = [
    'G'  => 'Gluten (wheat/barley/oats)',
    'D'  => 'Dairy (milk/cheese/cream/butter)',
    'E'  => 'Eggs',
    'F'  => 'Fish',
    'N'  => 'Nuts (almonds)',
    'Se' => 'Sesame',
    'So' => 'Soy',
    'S'  => 'Sulphites',
];

$menu = [
    [
        'id'    => 'tacos',
        'title' => 'Taco trio',
        'note'  => '3 tacos',
        'items' => [
            [
                'name' => 'Steak taco', 'price' => '16.50', 'allergens' => [],
                'desc' => 'Corn tortilla filled with steak, guacamole & pico de gallo.',
                'extra' => [$salsaNote, 'Add cheese +€1.00'],
            ],
            [
                'name' => 'Chorizo taco', 'price' => '15.50', 'allergens' => [],
                'desc' => 'Corn tortilla, homemade chorizo, guacamole & pico de gallo.',
                'extra' => [$salsaNote, 'Add cheese +€1.00'],
            ],
            [
                'name' => 'Mushroom taco', 'price' => '15.50', 'allergens' => ['D'],
                'desc' => 'Corn tortilla filled with grilled mushrooms, guacamole & pico de gallo.',
                'extra' => [$salsaNote, 'Add cheese +€1.00'],
            ],
        ],
    ],
    [
        'id'    => 'mains',
        'title' => 'Mains',
        'note'  => '',
        'items' => [
            [
                'name' => 'Enchiladas verdes', 'price' => '17.00', 'allergens' => ['D'],
                'desc' => 'Three corn tortillas filled with chicken or mushrooms, covered with salsa verde, feta cheese, sour cream, red onion & avocado.',
                'extra' => [],
            ],
            [
                'name' => 'Flautas', 'price' => '17.50', 'allergens' => ['D'],
                'desc' => 'Four fried corn tortillas filled with mashed potatoes, mushrooms or chicken, with lettuce, red onion, sour cream, feta cheese, avocado & tomato.',
                'extra' => [$salsaNote],
            ],
            [
                'name' => 'The Celtic Aztec Burger', 'price' => '19.00', 'allergens' => ['G', 'D', 'E', 'S'],
                'desc' => 'Two 3oz smashed Irish beef burgers with bacon & onion jam, tomato, lettuce, avocado, cheddar cheese & homemade sauce. Served with fries.',
                'extra' => [],
            ],
            [
                'name' => 'Don Taco Signature Birria', 'price' => '24.50', 'allergens' => ['D'],
                'desc' => 'Four tacos dorados filled with beef slow cooked in Mexican chillies & spices, with onion, coriander & cheese. Served with consomé.',
                'extra' => [$salsaNote],
            ],
        ],
    ],
    [
        'id'    => 'sides',
        'title' => 'Sides',
        'note'  => '',
        'items' => [
            ['name' => 'Tortilla chips & guacamole', 'price' => '8.50', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Fries',                      'price' => '5.50', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Sweet potato fries',         'price' => '5.50', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Salsas',                     'price' => '3.00', 'allergens' => [], 'desc' => 'Verde, roja or negra.', 'extra' => []],
        ],
    ],
    [
        'id'    => 'kids',
        'title' => 'Kids meals',
        'note'  => 'under 13 only',
        'items' => [
            [
                'name' => 'Chicken tenders', 'price' => '12.00', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Served with fries & juice.', 'extra' => [],
            ],
            [
                'name' => 'Sausages', 'price' => '10.00', 'allergens' => ['S', 'G', 'So'],
                'desc' => 'Served with fries & juice.', 'extra' => [],
            ],
            [
                'name' => 'Quesadilla', 'price' => '12.50', 'allergens' => ['G', 'D'],
                'desc' => 'Flour tortilla filled with mozzarella & ham. Served with fries & juice.', 'extra' => [],
            ],
            [
                'name' => 'Kids pancakes', 'price' => '10.00', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Served with chocolate or maple syrup & juice.', 'extra' => [],
            ],
        ],
    ],
    [
        'id'    => 'bakery',
        'title' => 'Bakery',
        'note'  => '',
        'items' => [
            [
                'name' => 'Conchas', 'price' => '4.90', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Mexican soft and fluffy pastry decorated with a sugary crispy crust.', 'extra' => [],
            ],
            [
                'name' => 'Fruit scone', 'price' => '4.50', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Served with jam & butter.', 'extra' => [],
            ],
            [
                'name' => 'Plain scone', 'price' => '4.50', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Served with jam & butter.', 'extra' => [],
            ],
        ],
    ],
    [
        'id'    => 'dessert',
        'title' => 'Dessert',
        'note'  => '',
        'items' => [
            ['name' => 'Dessert of the day', 'price' => '9.50', 'allergens' => [], 'desc' => 'Ask us what is on today.', 'extra' => []],
        ],
    ],
];
