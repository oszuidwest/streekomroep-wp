<?php

/**
 * Covers the autocomplete option added to Contact Form 7 personal data fields.
 *
 * Run with: composer test:contact-form
 */

$registered_filters = [];

function add_filter(string $hook, string $callback): void
{
    global $registered_filters;

    $registered_filters[$hook][] = $callback;
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require __DIR__ . '/../lib/contact_form.php';

assert_same(
    ['zw_cf7_autocomplete_tag'],
    $registered_filters['wpcf7_form_tag'] ?? [],
    'The form tag filter was not registered.'
);

$options_of = static fn (array $tag): array => zw_cf7_autocomplete_tag($tag)['options'];

assert_same(['autocomplete:email'], $options_of(['basetype' => 'email', 'name' => 'email', 'options' => []]), 'Email fields get autocomplete:email.');
assert_same(['autocomplete:email'], $options_of(['basetype' => 'email', 'name' => 'uw-email', 'options' => []]), 'Email fields are matched on type, not on name.');
assert_same(['autocomplete:tel'], $options_of(['basetype' => 'tel', 'name' => 'tel', 'options' => []]), 'Telephone fields get autocomplete:tel.');
assert_same(['autocomplete:name'], $options_of(['basetype' => 'text', 'name' => 'naam', 'options' => []]), 'The name field gets autocomplete:name.');
assert_same(['class:x', 'autocomplete:email'], $options_of(['basetype' => 'email', 'name' => 'email', 'options' => ['class:x']]), 'Existing options are kept.');

assert_same([], $options_of(['basetype' => 'text', 'name' => 'onderwerp', 'options' => []]), 'Other text fields are untouched.');
assert_same([], $options_of(['basetype' => 'textarea', 'name' => 'bericht', 'options' => []]), 'Textareas are untouched.');
assert_same([], $options_of(['basetype' => 'text', 'name' => 'naam-vriend', 'options' => []]), 'Only exact name fields are matched.');
assert_same(['autocomplete:off'], $options_of(['basetype' => 'email', 'name' => 'email', 'options' => ['autocomplete:off']]), 'An explicit autocomplete option wins.');
assert_same(['autocomplete:given-name'], $options_of(['basetype' => 'text', 'name' => 'naam', 'options' => ['autocomplete:given-name']]), 'An explicit token wins over the default.');

$no_options = ['basetype' => 'email', 'name' => 'email'];
assert_same(['autocomplete:email'], $options_of($no_options), 'A tag without an options key is handled.');

echo "contact form autocomplete OK\n";
