<?php
declare(strict_types=1);

function icon(string $name): string {
    return '<i class="ti ti-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
}
