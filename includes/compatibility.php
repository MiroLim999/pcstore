<?php
/**
 * compatibility.php — Server-side compatibility checker.
 * Mirrors the JS logic in pcbuilder/script.js exactly.
 *
 * Usage:
 *   $result = check_build_compatibility($items, $mysqli);
 *   // $result = ['valid' => bool, 'errors' => [...], 'warnings' => [...]]
 */

if (basename($_SERVER['PHP_SELF']) === 'compatibility.php') {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * Estimate total system wattage (with 30% headroom).
 * Mirrors JS estimateWattage().
 */
function estimate_wattage(?array $cpu_attrs, ?array $gpu_attrs): int
{
    $cpu_tdp = $cpu_attrs['tdp'] ?? 0;
    $gpu_tdp = $gpu_attrs['tdp'] ?? 0;
    if (!$cpu_tdp && !$gpu_tdp) return 0;
    $baseline = 60; // mobo + RAM + storage + fans
    return (int)round(($cpu_tdp + $gpu_tdp + $baseline) * 1.3);
}

/**
 * Run full compatibility check on a build.
 *
 * @param array $items Associative array keyed by category slug:
 *   ['cpu' => product_id, 'cooler' => product_id, 'mobo' => ..., 'ram' => ..., 'gpu' => ..., 'ssd' => ..., 'psu' => ..., 'case' => ...]
 * @param mysqli $mysqli Database connection
 * @return array ['valid' => bool, 'errors' => string[], 'warnings' => string[]]
 */
function check_build_compatibility(array $items, mysqli $mysqli): array
{
    $errors = [];
    $warnings = [];

    // Load compatibility attributes for each selected product
    $attrs = [];
    foreach ($items as $category => $product_id) {
        if (!$product_id) continue;
        $attrs[$category] = get_product_compat_attrs((int)$product_id, $mysqli);
    }

    $cpu = $attrs['cpu'] ?? null;
    $cooler = $attrs['cooler'] ?? null;
    $mobo = $attrs['mobo'] ?? null;
    $ram = $attrs['ram'] ?? null;
    $gpu = $attrs['gpu'] ?? null;
    $ssd = $attrs['ssd'] ?? null;
    $psu = $attrs['psu'] ?? null;
    $case = $attrs['case'] ?? null;

    // 1. CPU ↔ Motherboard socket match
    if ($cpu && $mobo) {
        if (!empty($cpu['socket']) && !empty($mobo['socket']) && $cpu['socket'] !== $mobo['socket']) {
            $errors[] = "CPU socket ({$cpu['socket']}) does not match motherboard ({$mobo['socket']}).";
        }
    }

    // 2. Cooler ↔ CPU socket support
    if ($cpu && $cooler) {
        $supported_sockets = json_decode($cooler['supported_sockets'] ?? '[]', true) ?: [];
        if (!empty($supported_sockets) && !empty($cpu['socket'])) {
            if (!in_array($cpu['socket'], $supported_sockets, true)) {
                $errors[] = "CPU cooler does not support {$cpu['socket']} socket.";
            }
        }
        // Cooler TDP capacity
        if (!empty($cooler['max_tdp']) && !empty($cpu['tdp'])) {
            if ((int)$cooler['max_tdp'] < (int)$cpu['tdp']) {
                $errors[] = "CPU cooler TDP ({$cooler['max_tdp']}W) is too low for CPU ({$cpu['tdp']}W).";
            }
        }
    }

    // 3. RAM ↔ Motherboard memory type
    if ($ram && $mobo) {
        if (!empty($ram['memory_type']) && !empty($mobo['memory_type'])) {
            if ($ram['memory_type'] !== $mobo['memory_type']) {
                $errors[] = "RAM type ({$ram['memory_type']}) does not match motherboard ({$mobo['memory_type']}).";
            }
        }
        // RAM speed warning
        if (!empty($ram['memory_speed']) && !empty($mobo['max_memory_speed'])) {
            if ((int)$ram['memory_speed'] > (int)$mobo['max_memory_speed']) {
                $warnings[] = "RAM speed ({$ram['memory_speed']}MHz) exceeds motherboard max ({$mobo['max_memory_speed']}MHz). It will run at the lower speed.";
            }
        }
    }

    // 4. Case ↔ Motherboard form factor
    if ($case && $mobo) {
        $supported_ff = json_decode($case['supported_form_factors'] ?? '[]', true) ?: [];
        if (!empty($supported_ff) && !empty($mobo['form_factor'])) {
            if (!in_array($mobo['form_factor'], $supported_ff, true)) {
                $errors[] = "Case does not support {$mobo['form_factor']} motherboard form factor.";
            }
        }
    }

    // 5. Storage ↔ Motherboard M.2 slot
    if ($ssd && $mobo) {
        if (!empty($ssd['requires_m2']) && (int)$ssd['requires_m2'] === 1) {
            if (isset($mobo['m2_slots']) && (int)$mobo['m2_slots'] === 0) {
                $errors[] = "NVMe drive requires an M.2 slot but motherboard has none.";
            }
        }
    }

    // 6. PSU wattage check
    if ($psu && ($cpu || $gpu)) {
        $required = estimate_wattage($cpu, $gpu);
        if (!empty($psu['wattage']) && $required > 0) {
            if ((int)$psu['wattage'] < $required) {
                $warnings[] = "Recommended wattage ({$required}W) exceeds PSU capacity ({$psu['wattage']}W).";
            }
        }
    }

    // 7. CPU/GPU balance (bottleneck warning — not a hard error)
    if ($cpu && $gpu) {
        $cpu_tier = (int)($cpu['gaming_tier'] ?? 0);
        $gpu_tier = (int)($gpu['gaming_tier'] ?? 0);
        if ($cpu_tier && $gpu_tier) {
            if ($gpu_tier - $cpu_tier >= 2) {
                $warnings[] = "CPU may bottleneck this GPU in demanding games.";
            } elseif ($cpu_tier - $gpu_tier >= 2) {
                $warnings[] = "GPU is significantly weaker than CPU — consider upgrading the GPU.";
            }
        }
    }

    return [
        'valid'    => empty($errors),
        'errors'   => $errors,
        'warnings' => $warnings,
    ];
}

/**
 * Load compatibility attributes for a product from the compatibility_attrs table.
 */
function get_product_compat_attrs(int $product_id, mysqli $mysqli): ?array
{
    $stmt = $mysqli->prepare("SELECT * FROM compatibility_attrs WHERE product_id = ?");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Calculate bottleneck percentage (mirrors JS calculateBottleneck).
 */
function calculate_bottleneck(?array $cpu_attrs, ?array $gpu_attrs): ?array
{
    if (!$cpu_attrs || !$gpu_attrs) return null;

    $cpu_score = (int)($cpu_attrs['gaming_score'] ?? 0);
    $gpu_score = (int)($gpu_attrs['gaming_score'] ?? 0);
    if (!$cpu_score || !$gpu_score) return null;

    $stronger = max($cpu_score, $gpu_score);
    $weaker = min($cpu_score, $gpu_score);
    $pct = (int)round((($stronger - $weaker) / $stronger) * 100);

    $limited_by = 'none';
    if ($cpu_score < $gpu_score) $limited_by = 'cpu';
    elseif ($gpu_score < $cpu_score) $limited_by = 'gpu';

    // Severity tiers
    if ($pct <= 5) $severity = 'excellent';
    elseif ($pct <= 12) $severity = 'balanced';
    elseif ($pct <= 20) $severity = ($limited_by === 'cpu') ? 'moderate' : 'minor';
    elseif ($pct <= 35) $severity = ($limited_by === 'cpu') ? 'severe' : 'moderate';
    else $severity = 'severe';

    return [
        'pct'        => $pct,
        'limited_by' => $limited_by,
        'severity'   => $severity,
    ];
}
