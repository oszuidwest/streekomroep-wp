<?php

/** Regression coverage for deduplicating articles and their attached fragments in search. */

$is_admin = false;
$article_ids = [];
$post_meta = [];
$secondary_query_args = null;
$primed_post_ids = [];
$registered_actions = [];

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName.NotCamelCaps -- WordPress core test double.
class WP_Query
{
    public array $posts = [];
    private array $query_vars;
    private bool $main_query;
    private bool $search;

    public function __construct(array $query_vars = [])
    {
        global $article_ids, $secondary_query_args;

        $this->query_vars = $query_vars;
        $this->main_query = $query_vars === [];
        $this->search = $query_vars === [];

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
        return $this->main_query;
    }

    public function is_search(): bool
    {
        return $this->search;
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
    global $is_admin;

    return $is_admin;
}

function update_meta_cache(string $type, array $ids): void
{
    global $primed_post_ids;

    if ($type === 'post') {
        $primed_post_ids = $ids;
    }
}

function get_post_meta(int $post_id, string $key, bool $single)
{
    global $post_meta;

    return $post_meta[$post_id][$key] ?? '';
}

function absint($value): int
{
    return abs((int) $value);
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
assert_same('ids', $secondary_query_args['fields'], 'The article probe fetched more than IDs.');
assert_same(-1, $secondary_query_args['posts_per_page'], 'The article probe did not inspect every matching article.');
assert_same([11, 12, 13], $primed_post_ids, 'The matching article metadata was not primed in one batch.');
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
