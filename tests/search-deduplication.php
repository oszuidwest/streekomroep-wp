<?php

/**
 * Regression coverage for deduplicating articles and their attached fragments in search.
 *
 * Run with: composer test:search
 */

$article_ids = [];
$post_meta = [];
$secondary_query_args = null;
$registered_actions = [];

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName.NotCamelCaps -- WordPress core test double.
class WP_Query
{
    public array $posts = [];
    private array $query_vars;
    private bool $is_main;

    public function __construct(array $query_vars = [])
    {
        global $article_ids, $secondary_query_args;

        $this->query_vars = $query_vars;
        $this->is_main = $query_vars === [];

        if ($query_vars !== []) {
            $secondary_query_args = $query_vars;
            $this->posts = $article_ids;
        }
    }

    public function get(string $key)
    {
        return $this->query_vars[$key] ?? null;
    }

    public function set(string $key, $value): void
    {
        $this->query_vars[$key] = $value;
    }

    public function is_main_query(): bool
    {
        return $this->is_main;
    }

    public function is_search(): bool
    {
        return $this->is_main;
    }
}
// phpcs:enable

function add_action(string $hook, string $callback): void
{
    global $registered_actions;

    $registered_actions[$hook][] = $callback;
}

function is_admin(): bool
{
    return false;
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress core test double.
// ACF stores the relationship as a serialized array in postmeta; the double serves it like MySQL would.
$wpdb = new class {
    public string $postmeta = 'wp_postmeta';
    private array $requested_ids = [];

    public function prepare(string $query, $args): string
    {
        $this->requested_ids = (array) $args;

        return $query;
    }

    public function get_col(string $query): array
    {
        global $post_meta;

        $values = [];
        foreach ($this->requested_ids as $post_id) {
            if (isset($post_meta[$post_id]['post_gekoppeld_fragment'])) {
                $values[] = serialize($post_meta[$post_id]['post_gekoppeld_fragment']);
            }
        }

        return $values;
    }
};
// phpcs:enable

function maybe_unserialize($data)
{
    $unserialized = @unserialize((string) $data);

    return $unserialized !== false ? $unserialized : $data;
}

function wp_parse_id_list($list): array
{
    return array_unique(array_map(static fn ($id) => abs((int) $id), (array) $list));
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

require __DIR__ . '/../lib/search.php';

assert_same(
    ['zw_exclude_linked_fragments_from_search'],
    $registered_actions['pre_get_posts'] ?? [],
    'The search deduplication hook was not registered.'
);

$main_query = new WP_Query();
$main_query->set('s', 'gemeenteraad');
$main_query->set('post__not_in', [90]);
$article_ids = [11, 12, 13];
$post_meta = [
    11 => ['post_gekoppeld_fragment' => ['101']],
    12 => ['post_gekoppeld_fragment' => ['102']],
    13 => ['post_gekoppeld_fragment' => ['101']],
];

zw_exclude_linked_fragments_from_search($main_query);

assert_same('gemeenteraad', $secondary_query_args['s'], 'The article probe did not reuse the search term.');
assert_same('post', $secondary_query_args['post_type'], 'The article probe was not limited to articles.');
assert_same(-1, $secondary_query_args['posts_per_page'], 'The article probe did not inspect every matching article.');
assert_same([90, 101, 102], $main_query->get('post__not_in'), 'Linked fragments were not excluded exactly once.');

$article_ids = [];
$secondary_query_args = null;
$fragment_only_query = new WP_Query();
$fragment_only_query->set('s', 'unieke fragmenttitel');

zw_exclude_linked_fragments_from_search($fragment_only_query);

assert_same([], (array) $fragment_only_query->get('post__not_in'), 'A standalone matching fragment was excluded.');

$secondary_query_args = null;
$empty_query = new WP_Query();
$empty_query->set('s', '   ');

zw_exclude_linked_fragments_from_search($empty_query);

assert_same(null, $secondary_query_args, 'An empty search unnecessarily ran the article probe.');

echo 'OK: linked fragments are deduplicated without hiding standalone fragment matches' . PHP_EOL;
