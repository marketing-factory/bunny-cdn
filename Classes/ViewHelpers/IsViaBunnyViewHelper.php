<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\ViewHelpers;

use TYPO3\CMS\Core\Context\Context;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Whether the current request actually arrived through Bunny's edge, as
 * opposed to merely being configured for it (see {@see IsActiveViewHelper}).
 *
 * ```
 * <f:if condition="{bunny:isViaBunny()}">...</f:if>
 * ```
 */
final class IsViaBunnyViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly Context $context) {}

    public function render(): bool
    {
        return (bool)$this->context->getPropertyFromAspect('bunnyCdn', 'viaBunny', false);
    }
}
