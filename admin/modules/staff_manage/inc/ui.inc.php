<?php
/**
 * UI helper functions for Staff Manage module.
 */

if (!function_exists('sm_render_page_header')) {
    function sm_render_page_header(string $title, string $subtitle = '', array $actions = []): void
    {
        echo '<div class="sm-page-header">';
        echo '<div>
                <h1>' . htmlspecialchars($title) . '</h1>';
        if ($subtitle) {
            echo '<p>' . htmlspecialchars($subtitle) . '</p>';
        }
        echo '</div>';
        if ($actions) {
            echo '<div class="sm-actions">';
            foreach ($actions as $action) {
                $label = htmlspecialchars($action['label']);
                $href = htmlspecialchars($action['href'] ?? '#');
                $icon = htmlspecialchars($action['icon'] ?? '');
                $class = htmlspecialchars($action['class'] ?? '');
                if ($icon) {
                    $icon = '<i class="' . $icon . '"></i>';
                }
                echo '<a class="sm-btn ' . $class . '" href="' . $href . '">' . $icon . '<span>' . $label . '</span></a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('sm_render_alert')) {
    function sm_render_alert(string $message, string $type = 'info'): void
    {
        echo '<div class="sm-alert sm-alert-' . htmlspecialchars($type) . '">' . $message . '</div>';
    }
}

if (!function_exists('sm_badge')) {
    function sm_badge(string $text, string $variant = 'default'): string
    {
        return '<span class="sm-badge sm-badge-' . htmlspecialchars($variant) . '">' . htmlspecialchars($text) . '</span>';
    }
}

if (!function_exists('sm_empty_state')) {
    function sm_empty_state(string $message): void
    {
        echo '<div class="sm-empty">';
        echo '<div class="sm-empty-icon"><i class="fas fa-inbox"></i></div>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '</div>';
    }
}

if (!function_exists('sm_format_datetime')) {
    function sm_format_datetime(?string $value, string $format = 'd M Y H:i'): string
    {
        if (!$value) {
            return '-';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }
        return date($format, $timestamp);
    }
}

if (!function_exists('sm_format_date')) {
    function sm_format_date(?string $value, string $format = 'd M Y'): string
    {
        if (!$value) {
            return '-';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }
        return date($format, $timestamp);
    }
}

if (!function_exists('sm_status_badge')) {
    function sm_status_badge(string $status, array $map): string
    {
        $label = $map[$status]['label'] ?? ucfirst($status);
        $variant = $map[$status]['variant'] ?? 'default';
        return sm_badge($label, $variant);
    }
}

if (!function_exists('sm_render_cards')) {
    function sm_render_cards(array $cards): void
    {
        echo '<div class="sm-card-grid">';
        foreach ($cards as $card) {
            echo '<div class="sm-card">';
            if (!empty($card['icon'])) {
                echo '<div class="sm-card-icon"><i class="' . htmlspecialchars($card['icon']) . '"></i></div>';
            }
            echo '<div class="sm-card-body">';
            echo '<p class="sm-card-title">' . htmlspecialchars($card['title']) . '</p>';
            echo '<h3>' . htmlspecialchars((string)$card['value']) . '</h3>';
            if (!empty($card['meta'])) {
                echo '<span class="sm-card-meta">' . htmlspecialchars($card['meta']) . '</span>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('sm_asset_tags')) {
    function sm_asset_tags(array $styles, array $scripts): void
    {
        foreach ($styles as $style) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($style) . '">';
        }
        foreach ($scripts as $script) {
            echo '<script src="' . htmlspecialchars($script) . '"></script>';
        }
    }
}
