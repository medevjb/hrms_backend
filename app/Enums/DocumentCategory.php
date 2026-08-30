<?php

namespace App\Enums;

/**
 * docs/PRD.md §82 — the V1 categories for a stored employee file.
 */
enum DocumentCategory: string
{
    case Contract = 'CONTRACT';
    case Identification = 'IDENTIFICATION';
    case Certification = 'CERTIFICATION';
    case Performance = 'PERFORMANCE';
    case Other = 'OTHER';
}
