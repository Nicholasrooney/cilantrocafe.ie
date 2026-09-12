<?php
/*
 * Menu items and prices. To change a price, edit the 'price' value.
 * Allergen codes go in 'allergens' as an array, e.g. ['G', 'D'].
 *
 * Transcribed from the café's printed menu boards. Allergen codes are copied
 * exactly as printed — do not add or guess codes here, check the kitchen first.
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
        'id'    => 'breakfast',
        'title' => 'Breakfast',
        'note'  => 'served until 12:00',
        'items' => [
            [
                'name' => 'Pancakes', 'price' => '15.00', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Fluffy pancakes served with fresh berries, bacon, maple syrup or chocolate.',
                'extra' => [],
            ],
            [
                'name' => 'French toast', 'price' => '15.50', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Brioche bread topped with berries, cream, maple syrup & bacon.',
                'extra' => [],
            ],
            [
                'name' => 'Build your own omelette', 'price' => '14.50', 'allergens' => ['E', 'D'],
                'desc' => '3 egg omelette with any 3 toppings: pico de gallo, mushroom, onion, chorizo, bacon, ham, cheddar or mozzarella.',
                'extra' => ['Extra toppings +€1.50'],
            ],
            [
                'name' => 'Huevos rancheros', 'price' => '15.50', 'allergens' => ['E', 'D'],
                'desc' => 'Two fried eggs on fried corn tortillas and beans, covered with salsa roja, feta cheese, sour cream & avocado.',
                'extra' => [],
            ],
            [
                'name' => 'Breakfast burrito', 'price' => '15.50', 'allergens' => ['G', 'D', 'E', 'S'],
                'desc' => 'Flour tortilla filled with cheese, homemade chorizo, scrambled egg, Mexican rice, homemade beans & sour cream.',
                'extra' => [$salsaNote],
            ],
            [
                'name' => 'Avocado toast', 'price' => '10.95', 'allergens' => ['G', 'D', 'E', 'F', 'N'],
                'desc' => 'Sourdough bread with smashed avocado, rocket, chilli oil & eggs: poached, scrambled or fried.',
                'extra' => ['Add bacon +€3.50 or salmon +€5.00'],
            ],
            [
                'name' => 'Full Irish breakfast', 'price' => '16.90', 'allergens' => ['G', 'D', 'E', 'S', 'So'],
                'desc' => 'Two fried eggs, two bacon, two sausages, black and white pudding, beans, roasted mushrooms & hashbrown. Served with sourdough bread.',
                'extra' => [],
            ],
            [
                'name' => 'Mini Irish breakfast', 'price' => '13.00', 'allergens' => ['G', 'D', 'E', 'S', 'So'],
                'desc' => 'One fried egg, one bacon, two sausages & beans. Served with sourdough bread.',
                'extra' => [],
            ],
            [
                'name' => 'Morning munchie', 'price' => '15.00', 'allergens' => ['G', 'D', 'E', 'S', 'So'],
                'desc' => 'Homemade telera with egg, sausage, bacon & relish. Served with fries.',
                'extra' => ['Served all day'],
            ],
            [
                'name' => 'Chilaquiles', 'price' => '14.90', 'allergens' => ['D', 'S', 'E'],
                'desc' => 'Crispy tortilla chips with salsa verde or roja, two fried eggs, beans, feta cheese, sour cream, red onion & avocado.',
                'extra' => ['Served all day', 'Add chicken or steak +€3.00'],
            ],
        ],
    ],
    [
        'id'    => 'tortas',
        'title' => 'Tortas',
        'note'  => 'Mexican homemade telera bread, served after 12:00',
        'items' => [
            [
                'name' => 'Ham & cheese torta', 'price' => '15.50', 'allergens' => ['G', 'D', 'S'],
                'desc' => 'Homemade telera filled with cooked ham, cheddar, lettuce, tomato, guacamole & chipotle salsa. Served with fries.',
                'extra' => [],
            ],
            [
                'name' => 'Mushroom torta', 'price' => '15.50', 'allergens' => ['G', 'D', 'S'],
                'desc' => 'Homemade telera filled with grilled mushrooms, mozzarella, homemade beans, guacamole & chipotle salsa. Served with fries.',
                'extra' => [],
            ],
            [
                'name' => 'Chicken torta', 'price' => '15.50', 'allergens' => ['G', 'D', 'S'],
                'desc' => 'Homemade telera filled with grilled chicken, mozzarella, lettuce, tomato, guacamole & chipotle salsa. Served with fries.',
                'extra' => [],
            ],
            [
                'name' => 'Chorizo torta', 'price' => '15.50', 'allergens' => ['G', 'D', 'S'],
                'desc' => 'Homemade telera filled with homemade chorizo, mozzarella, lettuce, tomato, guacamole & chipotle salsa. Served with fries.',
                'extra' => [],
            ],
            [
                'name' => 'Steak torta', 'price' => '16.50', 'allergens' => ['G', 'D'],
                'desc' => 'Homemade telera filled with steak, mozzarella, homemade beans, lettuce, tomato, guacamole & chipotle salsa. Served with fries.',
                'extra' => [],
            ],
        ],
    ],
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
        'id'    => 'soups-salads',
        'title' => 'Soups & salads',
        'note'  => '',
        'items' => [
            [
                'name' => 'Soup of the day', 'price' => '9.50', 'allergens' => ['G', 'D'],
                'desc' => 'Served with soda bread.', 'extra' => [],
            ],
            [
                'name' => 'Caesar salad', 'price' => '14.00', 'allergens' => ['G', 'D', 'E'],
                'desc' => 'Romaine lettuce, croutons, Caesar dressing & parmesan cheese.',
                'extra' => ['Add chicken +€3.00'],
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
    [
        'id'    => 'coffee',
        'title' => 'Coffee & tea',
        'note'  => '',
        'items' => [
            ['name' => 'Espresso',          'price' => '3.50', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Americano',         'price' => '3.70', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Flat white',        'price' => '4.10', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Cappuccino',        'price' => '4.30', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Latte',             'price' => '4.30', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Mocha',             'price' => '4.50', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Chai latte',        'price' => '4.50', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Matcha latte',      'price' => '4.90', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Tea',               'price' => '3.00', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Herbal tea',        'price' => '4.00', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Hot chocolate',     'price' => '4.00', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Kids hot chocolate','price' => '2.00', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Extra shot',        'price' => '1.50', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Make it iced',      'price' => '0.25', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Alternative milk',  'price' => '0.50', 'allergens' => [], 'desc' => '', 'extra' => []],
        ],
    ],
    [
        'id'    => 'cold-drinks',
        'title' => 'Cold drinks',
        'note'  => '',
        'items' => [
            ['name' => 'Coca-Cola',                 'price' => '3.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Diet Coke',                 'price' => '3.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Coke Zero',                 'price' => '3.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Sprite',                    'price' => '3.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Fanta Orange',              'price' => '3.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Still water',               'price' => '3.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Sparkling water',           'price' => '3.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Agua fresca',               'price' => '4.90', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Fruit Shoot orange',        'price' => '2.65', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Fruit Shoot blackcurrant',  'price' => '2.65', 'allergens' => [], 'desc' => '', 'extra' => []],
        ],
    ],
    [
        'id'    => 'wine',
        'title' => 'Wine',
        'note'  => '',
        'items' => [
            [
                'name' => 'Mancura Etnia Sauvignon Blanc', 'price' => '10.95', 'allergens' => [],
                'desc' => 'Central Valley, Chile. Crisp and refreshing, with mouthwatering fruit and a lively finish.',
                'extra' => ['White · 187ml'],
            ],
            [
                'name' => 'Mancura Etnia Cabernet Sauvignon', 'price' => '10.95', 'allergens' => [],
                'desc' => 'Central Valley, Chile. Smooth and approachable, with ripe berry fruit and soft tannins.',
                'extra' => ['Red · 187ml'],
            ],
            [
                'name' => 'Serena Prosecco', 'price' => '10.95', 'allergens' => [],
                'desc' => 'Crisp apple and pear fruit with subtle floral notes.',
                'extra' => ['Sparkling · 20cl snipe'],
            ],
            [
                'name' => 'Sachetto Prosecco Rosé', 'price' => '11.95', 'allergens' => [],
                'desc' => 'Fresh and elegant, with delicate berry fruit.',
                'extra' => ['Sparkling · 20cl snipe'],
            ],
        ],
    ],
    [
        'id'    => 'cocktails',
        'title' => 'Cocktails',
        'note'  => '',
        'items' => [
            ['name' => 'Mimosa',         'price' => '10.00', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Aperol Spritz',  'price' => '12.00', 'allergens' => [], 'desc' => '', 'extra' => []],
            ['name' => 'Mimosa mocktail','price' => '10.00', 'allergens' => [], 'desc' => 'Alcohol free.', 'extra' => []],
        ],
    ],
];
