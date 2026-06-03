<?php

namespace Tests;

use App\Support\MySqlFullTextQuery;

class MySqlFullTextQueryTest extends TestCase
{
    use CreatesApplication;

    public function test_trailing_hyphen_after_truncation_is_safe(): void
    {
        $term = 'linear-pendant lighting This living room is conceived as a refined contemporary modern interior built on a warm neutral-';

        $boolean = MySqlFullTextQuery::toBooleanMode($term);

        $this->assertNotNull($boolean);
        $this->assertStringNotContainsString('-*', $boolean);
        $this->assertStringContainsString('+linear*', $boolean);
        $this->assertStringContainsString('+pendant*', $boolean);
        $this->assertStringContainsString('+neutral*', $boolean);
    }

    public function test_hyphenated_subtype_splits_into_terms(): void
    {
        $boolean = MySqlFullTextQuery::toBooleanMode('arc-floor-lamp lighting');

        $this->assertSame('+arc* +floor* +lamp* +lighting*', $boolean);
    }

    public function test_empty_after_sanitization_returns_null(): void
    {
        $this->assertNull(MySqlFullTextQuery::toBooleanMode('---'));
        $this->assertNull(MySqlFullTextQuery::toBooleanMode(''));
    }
}
