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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

@trigger_error(
    \sprintf(
        'This trait (%s) is deprecated use %s',
        'Blc\Component\Blc\Administrator\Traits\FieldAwareTrait',
        'Blc\Component\Blc\Administrator\Traits\CustomFieldsTrait'
    ),
    E_USER_DEPRECATED
);
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;

trait FieldAwareTrait
{
    private $contentFields          = [];
    private $contentLinks           = [];
    private $allowedFields          = [];
    private $extraUrlIds            = [];
    private $fieldToType            = null;
    private $newUrl                 = null;
    private $oldUrl                 = null;
    private $parserInstance         = null;
    protected string $splitOption   = "#(;|,|\r\n|\n|\r)#";

    public function __construct()
    {
        $defaultFields = ['text' => 0, 'textarea' => 0, 'editor' => 1, 'url' => 1, 'media' => 1, 'subform' => 1];
        Factory::getApplication()->enqueueMessage('This code \'FieldAwareTrait\' is outdated, please update all extensions', 'error');
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    protected function getUnsynchedRows()
    {
    }

    protected function getFieldValue(int $fieldId, int $itemId): \stdClass
    {
        return new \stdClass();
    }


    protected function parseSubForm(object|string $subform): object
    {
        return new \stdClass();
    }
    /**
     *  @since 24.44.6611
     *
     * helper function to load the allowed field types for each #__fields.id
     * custom fields are pnly referenced by their #__fields.id, the type is not included.
     *
     *
     */
    protected function loadFieldToType()
    {
    }
    /**
     *  @since 24.44.6611
     *
     * similair to parseSubForm, this version modifies the subform object
     */
    protected function replaceSubForm(object|string $subform): object
    {

        return new \stdClass();
        //   exit;
    }


    protected function parseCustomField($row)
    {
        return new \stdClass();
    }

    protected function parseContainer(int $id): void
    {
    }

    protected function getFieldModel()
    {
    }
    #[\Override]
    public function replaceLink(LinkTable $link, object $instance, string $newUrl): void
    {
    }

    protected function replaceCustomField($row)
    {
    }

    protected function parseContainerFields($rows): void
    {
    }
}
