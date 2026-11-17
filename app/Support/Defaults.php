<?php

namespace App\Support;

/**
 * Default values shared by requests, services and controllers.
 * Keeps magic numbers out of the application code.
 */
final class Defaults
{
    public const PRODUCTS_PER_PAGE = 15;

    public const MOVEMENTS_PER_PAGE = 20;

    public const LOW_STOCK_PER_PAGE = 20;

    public const LOW_STOCK_THRESHOLD = 10;
}
