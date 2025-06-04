<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *

 *
 */

namespace Blc\Component\Blc\Administrator\Traits;

use Blc\Component\Blc\Administrator\Blc\BlcParseController;
use Blc\Component\Blc\Administrator\Interface\BlcSetAltInterface as ALT_CODES;
use Blc\Component\Blc\Administrator\Table\LinkTable;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


trait BlcSetAltTrait
{
    /**
     * @since 25.44.7545
     *
     */
    public function canSetAlt(object $instance): bool
    {
        if (empty($this->canSetAltFields)) {
            throw new \RuntimeException(\sprintf('Class \'%2$s\' must set \'%1$s\'', __FUNCTION__, self::class));
        }
        if (empty($instance->field)) {
            return false;
        }
        $canReplace = $this->canSetAltFields[$instance->field] ?? ALT_CODES::BLC_REPLACE_ALT_NO;
        if ($canReplace === ALT_CODES::BLC_REPLACE_ALT_NO) {
            return false;
        }
        if ($canReplace === ALT_CODES::BLC_REPLACE_ALT_YES) {
            return true;
        }

        if (empty($instance->parser)) {
            return false;
        }

        if ($canReplace === ALT_CODES::BLC_REPLACE_ALT_PARSER) {
            $parseController =  BlcParseController::getInstance();
            $parser          = $parseController->getParser($instance->parser);
            if (! $parser) {
                return false;
            }
            return $parser->getCanSetAlt($instance->field);
        }
        //should never happen, unknow ALT_CODe
        return false;
    }
    /**
     * @since 25.44.7545
     *
     */
    public function getFieldsSetAlt(): array
    {
        return $this->canSetAltFields;
    }
    /**
     * This wil set the alt attribute for a given link
     * that might be a 'alt' attribute in html or fields like image_alt
     *
     * @param LinkTable $link
     * @param object $instance  - join of instance and synch
     * @param string $newAlt
     *
     * @since 25.44.7545
     *
     */
    public function setAlt(LinkTable $link, object $instance, string $newAlt): void
    {
        throw new \RuntimeException(\sprintf("Function %s in %s must be overriden", __FUNCTION__, self::class));
    }
}
