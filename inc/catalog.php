<?php
/*
 * Standard Issue Shirt Co. - catalog, prices and sales tax.
 * The order page reads this (through pay.php?catalog=1) to show options,
 * and pay.php uses the SAME numbers to set the amount charged. Change prices here only.
 */

const SI_PRICE_RULES = [
    'min_pieces'      => 12,   // smallest custom order
    'small_run_under' => 24,   // under this many pieces...
    'small_run_add'   => 2,    // ...add this per piece
    'size_add'        => ['2XL' => 2, '3XL' => 3, '4XL' => 4, '5XL' => 5, '6XL' => 6],
];

// Florida destination-based sales tax: 6% state + county surtax (DR-15DSS, calendar year 2026).
// Update every January from the new DR-15DSS.
const SI_TAX_RATES = [
    'Duval' => 0.075, 'Clay' => 0.075, 'Columbia' => 0.075,
    'Nassau' => 0.07, 'Baker' => 0.07, 'Flagler' => 0.07, 'Putnam' => 0.07,
    'St. Johns' => 0.065,
];
const SI_PICKUP_COUNTY = 'Duval'; // pickup at our Jacksonville shop

function si_catalog() {
    return [
        'styles' => [
            'PC54' => [
                'name' => 'Everyday Tee', 'brand' => 'Port & Company PC54', 'kind' => 'tee',
                'note' => '5.4 oz 100% cotton, classic fit',
                'price' => ['front' => 10, 'fb' => 14],
                'sizes' => ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL'],
                'colors' => ['White', 'Aquatic Blue', 'Ash', 'Athletic Heather', 'Athletic Kelly', 'Athletic Maroon', 'Black Heather', 'Bright Aqua', 'Candy Pink', 'Cardinal', 'Carolina Blue', 'Charcoal', 'Cherry Blossom', 'Clover Green', 'Coal Grey', 'Coral', 'Coyote Brown', 'Creme', 'Dark Chocolate Brown', 'Dark Green', 'Dark Heather Grey', 'Duck Brown', 'Flush Pink', 'Gold', 'Graphite Heather', 'Heather Athletic Maroon', 'Heather Dark Chocolate Brown', 'Heather Navy', 'Heather Purple', 'Heather Red', 'Heather Royal', 'Heather Sangria', 'Heathered Dusty Peach', 'Ivory', 'Jade Green', 'Jadeite', 'Jet Black', 'Kelly', 'Laurel Green', 'Lavender', 'Lemon Yellow', 'Light Blue', 'Light Sand', 'Lime', 'Medium Grey', 'Natural', 'Navy', 'Neon Blue', 'Neon Green', 'Neon Orange', 'Neon Pink', 'Neon Yellow', 'Neptune Blue', 'Oatmeal Heather', 'Olive', 'Olive Drab Green', 'Olive Drab Green Heather', 'Orange', 'Pale Blush', 'Purple', 'Red', 'Royal', 'S. Green', 'S. Orange', 'Sand', 'Sangria', 'Sapphire', 'Silver', 'Steel Blue', 'Stonewashed Blue', 'Teal', 'Team Purple', 'Tennessee Orange', 'Tidal Wave', 'True Celadon', 'True Navy', 'True Royal', 'Tundra Blue', 'Violet', 'Woodland Brown', 'Yellow', 'Zinnia'],
            ],
            'BC3001' => [
                'name' => 'Premium Tee', 'brand' => 'Bella+Canvas 3001', 'kind' => 'tee',
                'note' => '4.2 oz Airlume ring-spun cotton, retail fit',
                'price' => ['front' => 13, 'fb' => 17],
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'],
                'colors' => ['White', 'Aqua', 'Army', 'Ash', 'Asphalt', 'Atlantic', 'Autumn', 'Baby Blue', 'Berry', 'Black', 'Blue Storm', 'Brown', 'Burnt Orange', 'Canvas Red', 'Cardinal', 'Carolina Blue', 'Charity Pink', 'Chestnut', 'Clay', 'Cocoa', 'Columbia Blue', 'Cool Blue', 'Coral', 'Dark Grey', 'Dark Lavender', 'Dark Olive', 'Deep Teal', 'Dust', 'Dusty Blue', 'Electric Blue', 'Evergreen', 'Forest', 'Fuchsia', 'Gold', 'Hunter Green', 'Kelly', 'Lavender Blue', 'Lavender Dust', 'Leaf', 'Light Blue', 'Light Violet', 'Lilac', 'Maize Yellow', 'Marine', 'Maroon', 'Mauve', 'Military Green', 'Mint', 'Mustard', 'Natural', 'Navy', 'Ocean Blue', 'Olive', 'Orange', 'Orchid', 'Oxblood Black', 'Peach', 'Pebble Brown', 'Pine', 'Pink', 'Pink Gravel', 'Poppy', 'Purple Storm', 'Red', 'Royal Purple', 'Rust', 'Sage', 'Sand Dune', 'Silver', 'Slate', 'Soft Cream', 'Soft Pink', 'Solid Athletic Grey', 'Steel Blue', 'Storm', 'Sunset', 'Synthetic Green', 'Tan', 'Teal', 'Team Navy', 'Team Purple', 'Thyme', 'Toast', 'True Royal', 'Turquoise', 'Vintage Black', 'Vintage Brown', 'Vintage Denim', 'Vintage Navy', 'Vintage Red', 'Vintage White', 'Yellow'],
            ],
            'PC78' => [
                'name' => 'Everyday Crewneck', 'brand' => 'Port & Company PC78', 'kind' => 'crew',
                'note' => '7.8 oz 50/50 fleece',
                'price' => ['front' => 18, 'fb' => 22],
                'sizes' => ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL'],
                'colors' => ['White', 'Ash', 'Athletic Heather', 'Black Heather', 'Candy Pink', 'Carolina Blue', 'Charcoal', 'Cherry Blossom', 'Coyote Brown', 'Dark Chocolate Brown', 'Dark Green', 'Dark Heather Grey', 'Gold', 'Graphite Heather', 'Heather Navy', 'Heather Red', 'Heather Royal', 'Jet Black', 'Kelly', 'Light Blue', 'Light Sand', 'Lime', 'Maroon', 'Medium Grey', 'Natural', 'Navy', 'Neon Blue', 'Neon Pink', 'Neon Yellow', 'Olive Drab Green', 'Orange', 'Purple', 'Red', 'Royal', 'S. Orange', 'Sand', 'Sangria', 'Steel Blue', 'True Royal', 'Woodland Brown'],
            ],
            'BC3901' => [
                'name' => 'Premium Crewneck', 'brand' => 'Bella+Canvas 3901', 'kind' => 'crew',
                'note' => '8 oz sponge fleece raglan, retail fit',
                'price' => ['front' => 30, 'fb' => 38],
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'],
                'colors' => ['White', 'Ash', 'Asphalt', 'Athletic Heather', 'Black', 'Black Heather', 'Blue Storm', 'Cardinal', 'Carolina Blue', 'Dark Grey Heather', 'Deep Heather', 'Forest', 'Grey Acid Fleece', 'Grey Triblend', 'Heather Blue Lagoon', 'Heather Deep Teal', 'Heather Dust', 'Heather Forest', 'Heather Mustard', 'Heather Navy', 'Heather Olive', 'Heather Red', 'Heather True Royal', 'Lavender Blue', 'Maroon', 'Mauve', 'Military Green', 'Natural', 'Navy', 'Pink', 'Red', 'Silver', 'Team Purple', 'True Royal', 'Vintage Black'],
            ],
        ],
        'packs' => [
            'starter' => ['name' => 'Starter Crew Pack', 'price' => 595, 'tee' => 'PC54', 'crew' => 'PC78', 'tees' => 24, 'crews' => 12, 'label' => 'Starter Crew Pack ($595)'],
            'premium' => ['name' => 'Premium Crew Pack', 'price' => 850, 'tee' => 'BC3001', 'crew' => 'BC3901', 'tees' => 24, 'crews' => 12, 'label' => 'Premium Crew Pack ($850)'],
        ],
        'rules' => SI_PRICE_RULES,
    ];
}

function si_size_add($size) {
    return SI_PRICE_RULES['size_add'][$size] ?? 0;
}

/* Look up tax for a delivery. Returns ['ok'=>bool, 'county'=>..., 'rate'=>..., 'error'=>...] */
function si_tax_for($delivery, $zip) {
    if ($delivery === 'pickup') return ['ok' => true, 'county' => SI_PICKUP_COUNTY, 'rate' => SI_TAX_RATES[SI_PICKUP_COUNTY]];
    $zip = substr(preg_replace('/\D/', '', (string)$zip), 0, 5);
    if (strlen($zip) !== 5) return ['ok' => false, 'error' => 'Enter your 5-digit delivery ZIP code.'];
    static $zips = null;
    if ($zips === null) $zips = require __DIR__ . '/zips.php';
    if (isset($zips[$zip])) {
        $c = $zips[$zip];
        return ['ok' => true, 'county' => $c, 'rate' => SI_TAX_RATES[$c], 'zip' => $zip];
    }
    $p3 = (int)substr($zip, 0, 3);
    if (($p3 >= 300 && $p3 <= 319) || $p3 === 398 || $p3 === 399) {
        return ['ok' => false, 'georgia' => true, 'error' => 'For Georgia deliveries, text us at 904-382-5885 and we will send your total. Or choose pickup in Jacksonville.'];
    }
    return ['ok' => false, 'error' => 'That ZIP is outside our delivery area. Choose pickup in Jacksonville, or text 904-382-5885 and we will work it out.'];
}

/*
 * Price an order. $o = [
 *   'type' => 'pack'|'custom', 'pack' => 'starter'|'premium',
 *   'items' => [['style'=>'PC54','color'=>'White','print'=>'front'|'fb','sizes'=>['M'=>10,...]], ...],
 *   'delivery' => 'deliver'|'pickup', 'zip' => '32202', 'exempt' => bool ]
 * For a pack, items[0] is the tees and items[1] is the crewnecks (style and print are fixed).
 * Returns ['ok'=>true, 'lines'=>[...], 'subtotal', 'tax', 'total', ...] or ['ok'=>false,'error'=>...]
 */
function si_quote($o) {
    $cat = si_catalog();
    $type = ($o['type'] ?? '') === 'pack' ? 'pack' : 'custom';
    $items = array_values(is_array($o['items'] ?? null) ? $o['items'] : []);
    $lines = []; $subtotal = 0; $pieces = 0; $needs = [];

    $cleanItem = function ($it, $style) use ($cat) {
        $s = $cat['styles'][$style];
        $color = (string)($it['color'] ?? 'White');
        if (!in_array($color, $s['colors'], true)) return [null, 'Pick a color for the ' . $s['name'] . '.'];
        $sizes = []; $n = 0;
        foreach ((array)($it['sizes'] ?? []) as $sz => $q) {
            $q = (int)$q;
            if ($q < 0 || $q > 5000) return [null, 'Check the quantities.'];
            if ($q === 0) continue;
            if (!in_array($sz, $s['sizes'], true)) return [null, $sz . ' is not available in the ' . $s['name'] . '.'];
            $sizes[$sz] = $q; $n += $q;
        }
        return [['style' => $style, 'color' => $color, 'sizes' => $sizes, 'count' => $n], null];
    };

    if ($type === 'pack') {
        $pk = $cat['packs'][$o['pack'] ?? ''] ?? null;
        if (!$pk) return ['ok' => false, 'error' => 'Pick a Crew Pack.'];
        [$tee, $e1] = $cleanItem($items[0] ?? [], $pk['tee']);
        if ($e1) return ['ok' => false, 'error' => $e1];
        [$crew, $e2] = $cleanItem($items[1] ?? [], $pk['crew']);
        if ($e2) return ['ok' => false, 'error' => $e2];
        if ($tee['count'] !== $pk['tees']) return ['ok' => false, 'error' => 'Tee sizes need to add up to ' . $pk['tees'] . ' (you have ' . $tee['count'] . ').'];
        if ($crew['count'] !== $pk['crews']) return ['ok' => false, 'error' => 'Crewneck sizes need to add up to ' . $pk['crews'] . ' (you have ' . $crew['count'] . ').'];
        $lines[] = ['desc' => $pk['name'] . ' (front + back)', 'qty' => 1, 'unit' => $pk['price'], 'amount' => $pk['price']];
        $subtotal += $pk['price'];
        foreach ([$tee, $crew] as $it) {
            $s = $cat['styles'][$it['style']];
            $lines[] = ['desc' => $it['count'] . ' ' . $s['name'] . 's, ' . $it['color'] . ': ' . si_size_text($it['sizes']), 'qty' => 0, 'unit' => 0, 'amount' => 0, 'info' => true, 'item' => $it + ['print' => 'fb']];
            foreach ($it['sizes'] as $sz => $q) {
                $add = si_size_add($sz);
                if ($add) {
                    $lines[] = ['desc' => "$sz upcharge, " . $s['name'], 'qty' => $q, 'unit' => $add, 'amount' => $q * $add];
                    $subtotal += $q * $add;
                }
            }
        }
        $pieces = $tee['count'] + $crew['count'];
        $needs[] = $pk['label'];
        $printLabel = 'Front + Back';
    } else {
        if (!$items) return ['ok' => false, 'error' => 'Add at least one item.'];
        if (count($items) > 8) return ['ok' => false, 'error' => 'Up to 8 items per order. Text us for bigger orders.'];
        $clean = []; $prints = [];
        foreach ($items as $it) {
            $style = (string)($it['style'] ?? '');
            if (!isset($cat['styles'][$style])) return ['ok' => false, 'error' => 'Pick a shirt style.'];
            $print = ($it['print'] ?? '') === 'fb' ? 'fb' : 'front';
            [$c, $e] = $cleanItem($it, $style);
            if ($e) return ['ok' => false, 'error' => $e];
            if ($c['count'] === 0) continue;
            $c['print'] = $print; $clean[] = $c; $pieces += $c['count']; $prints[$print] = true;
        }
        if ($pieces < SI_PRICE_RULES['min_pieces']) return ['ok' => false, 'error' => 'Orders start at ' . SI_PRICE_RULES['min_pieces'] . ' pieces (you have ' . $pieces . ').'];
        $small = $pieces < SI_PRICE_RULES['small_run_under'] ? SI_PRICE_RULES['small_run_add'] : 0;
        $names = ['PC54' => 'Everyday Tees', 'BC3001' => 'Premium Tees', 'PC78' => 'Everyday Crewnecks', 'BC3901' => 'Premium Crewnecks'];
        foreach ($clean as $c) {
            $s = $cat['styles'][$c['style']];
            $base = $s['price'][$c['print']] + $small;
            $pl = $c['print'] === 'fb' ? 'front + back' : 'front';
            $lines[] = ['desc' => $s['name'] . ', ' . $c['color'] . ", $pl: " . si_size_text($c['sizes']), 'qty' => $c['count'], 'unit' => $base, 'amount' => $c['count'] * $base, 'item' => $c];
            $subtotal += $c['count'] * $base;
            foreach ($c['sizes'] as $sz => $q) {
                $add = si_size_add($sz);
                if ($add) { $lines[] = ['desc' => "$sz upcharge, " . $s['name'], 'qty' => $q, 'unit' => $add, 'amount' => $q * $add]; $subtotal += $q * $add; }
            }
            $needs[] = $names[$c['style']];
        }
        $printLabel = count($prints) > 1 ? 'Not sure' : (isset($prints['fb']) ? 'Front + Back' : 'Front only');
        if ($small) $lines[] = ['desc' => "Includes \$$small/piece for orders under " . SI_PRICE_RULES['small_run_under'] . ' pieces', 'qty' => 0, 'unit' => 0, 'amount' => 0, 'info' => true];
    }

    $exempt = !empty($o['exempt']);
    $tax = si_tax_for((string)($o['delivery'] ?? 'deliver'), (string)($o['zip'] ?? ''));
    $taxAmt = 0;
    if ($tax['ok'] && !$exempt) $taxAmt = round($subtotal * $tax['rate'], 2);
    return [
        'ok' => true, 'type' => $type, 'lines' => $lines, 'pieces' => $pieces,
        'subtotal' => round($subtotal, 2),
        'tax_ok' => $tax['ok'], 'tax_error' => $tax['error'] ?? null,
        'county' => $tax['county'] ?? null, 'rate' => $tax['rate'] ?? null,
        'exempt' => $exempt, 'tax' => $taxAmt,
        'total' => round($subtotal + $taxAmt, 2),
        'needs' => array_values(array_unique($needs)), 'print_label' => $printLabel,
    ];
}

function si_size_text($sizes) {
    $p = [];
    foreach ($sizes as $sz => $q) $p[] = "$q $sz";
    return implode(', ', $p);
}
