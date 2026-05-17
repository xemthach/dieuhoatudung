<?php

return [
    'claims' => [
        'vat' => [
            'patterns' => ['/\\bvat\\b/iu'],
            'required_source' => 'policy.vat',
            'allow_if' => ['product.vat_enabled', 'settings.vat_enabled'],
            'rewrite_strategy' => 'remove_or_replace_with_pricing_policy',
            'severity' => 'block',
        ],
        'mien_phi' => [
            'patterns' => ['/\\bmien\\s+phi\\b/iu', '/\\bmiễn\\s+phí\\b/iu'],
            'required_source' => 'policy.free_claim',
            'allow_if' => ['policy.free_claim'],
            'rewrite_strategy' => 'replace_with_contact_for_installation_advice',
            'severity' => 'rewrite',
        ],
        'chinh_hang' => [
            'patterns' => ['/\\bchinh\\s+hang\\b/iu', '/\\bchính\\s+hãng\\b/iu'],
            'required_source' => 'policy.official_goods',
            'allow_if' => ['policy.official_goods'],
            'rewrite_strategy' => 'replace_with_verified_product_information',
            'severity' => 'rewrite',
        ],
        'bao_hanh' => [
            'patterns' => ['/\\bbao\\s+hanh\\b/iu', '/\\bbảo\\s+hành\\b/iu'],
            'required_source' => 'product.warranty_info',
            'allow_if' => ['product.warranty_info'],
            'rewrite_strategy' => 'remove_warranty_duration_without_policy',
            'severity' => 'rewrite',
        ],
        'tot_nhat' => [
            'patterns' => ['/\\btot\\s+nhat\\b/iu', '/\\btốt\\s+nhất\\b/iu'],
            'required_source' => 'policy.best_claim',
            'allow_if' => ['policy.best_claim'],
            'rewrite_strategy' => 'replace_with_neutral_quality_statement',
            'severity' => 'rewrite',
        ],
        'gia_tot_nhat' => [
            'patterns' => ['/\\bgia\\s+tot\\s+nhat\\b/iu', '/\\bgiá\\s+tốt\\s+nhất\\b/iu'],
            'required_source' => 'policy.price_claim',
            'allow_if' => ['policy.price_claim'],
            'rewrite_strategy' => 'replace_with_contact_for_quote',
            'severity' => 'rewrite',
        ],
        'tiet_kiem_nhat' => [
            'patterns' => ['/\\btiet\\s+kiem\\s+nhat\\b/iu'],
            'required_source' => 'policy.energy_claim',
            'allow_if' => ['policy.energy_claim'],
            'rewrite_strategy' => 'replace_with_neutral_efficiency_statement',
            'severity' => 'rewrite',
        ],
        'vuot_troi' => [
            'patterns' => ['/\\bvuot\\s+troi\\b/iu', '/\\bvượt\\s+trội\\b/iu'],
            'required_source' => 'policy.performance_claim',
            'allow_if' => ['policy.performance_claim'],
            'rewrite_strategy' => 'replace_with_application_fit_statement',
            'severity' => 'rewrite',
        ],
        'co_cq' => [
            'patterns' => ['/\\b(co\\/cq|co\\s+cq)\\b/iu'],
            'required_source' => 'policy.co_cq',
            'allow_if' => ['policy.co_cq'],
            'rewrite_strategy' => 'remove_certification_claim',
            'severity' => 'block',
        ],
        'percent_100' => [
            'patterns' => ['/100\\s*%/u'],
            'required_source' => 'policy.percent_100',
            'allow_if' => ['policy.percent_100'],
            'rewrite_strategy' => 'remove_absolute_claim',
            'severity' => 'block',
        ],
    ],
];
