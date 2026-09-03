<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\ViewHelpers;

use TYPO3\CMS\Core\Context\Context;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Whether Bunny CDN is configured/active for the current site.
 *
 * ```
 * <f:if condition="{bunny:isActive()}">...</f:if>
 * ```
 */
final class IsActiveViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly Context $context) {}

    public function render(): bool
    {
        return (bool)$this->context->getPropertyFromAspect('bunnyCdn', 'active', false);
    }
}
