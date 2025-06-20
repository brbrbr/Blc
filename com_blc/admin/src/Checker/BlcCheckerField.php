<?php

/**
 * @version   24.44
 * @package    Com_Blc
 * @author     Bram <bram@brokenlinkchecker.dev>
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 *
 * this checker resets redirecting links
 * For example for affiliate links
 *
 */

namespace Blc\Component\Blc\Administrator\Checker;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Blc\Component\Blc\Administrator\Blc\BlcModule;
use Blc\Component\Blc\Administrator\Helper\UrlHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;

class BlcCheckerField extends BlcModule implements BlcCheckerInterface
{
    /**
     * Property instance.
     *
     * @var  BlcModule
     *
     */

    use DatabaseAwareTrait;
    protected static ?BlcModule $instance = null;

    protected $pathPrefixes;
    private const BLCCHECKERFIELD_INVALID = -1;
    private const BLCCHECKERFIELD_VALID = 1;
    private const BLCCHECKERFIELD_UNKOWN = 0;





    public function canCheckLink(LinkTable $linkItem): int
    {
        //do not check checked links
        if ($linkItem->http_code !== self::BLC_CHECK_UNSET) {
            return  self::BLC_CHECK_FALSE;
        }

        $scheme = parse_url($linkItem->url, PHP_URL_SCHEME);
        //for internal URL the scheme might be empty (for example when called from BlcParseController)
        //same for the host. Can't check here.
        return \in_array($scheme, ['sqlfield']) ? self::BLC_CHECK_TRUE : self::BLC_CHECK_FALSE;
    }

    /**
     * 
     * @since __DEPLOY_VERSION__
     */

    public static function buildPseudoFieldLink(string $type, int $id, mixed $value): string
    {
        $store = htmlentities(json_encode($value));
        return


            "{$type}field://{$id}/$store";
    }
    /**
     * 
     * @since __DEPLOY_VERSION__
     */
    public static function parsePseudoFieldLink(string $url): array
    {
        $parsed = new Uri($url);
        //this will cleanup any leading /'s and queries and fragments
        $fieldId   = (int)$parsed->getHost() ?? 0;
        $fieldType   = preg_replace('#field$#', '',     $parsed->getScheme() ?? '');

        $fieldValuesString   = html_entity_decode(trim($parsed->getPath() ?? '', '/'));
        $fieldValues = (array)json_decode($fieldValuesString, true);
        return compact(['fieldId', 'fieldType', 'fieldValues']);
    }

    public function checkLink(LinkTable &$linkItem): void
    {

        $linkItem->log[] = self::class;
        extract($this->parsePseudoFieldLink($linkItem->url));

        switch ($fieldType) {
            case 'sql':
                $result = $this->checkSqlField($fieldId, $fieldValues);
                break;
            default:
                $result = self::BLCCHECKERFIELD_UNKOWN;
        }
        $linkItem->log[] = 'Checked by BlcCheckerField';

        switch ($result) {
            case self::BLCCHECKERFIELD_INVALID:
                $linkItem->http_code = self::BLC_INVALID_FIELD_HTTP_CODE;
                $linkItem->broken = self::BLC_BROKEN_TRUE;
                break;
                break;
            case self::BLCCHECKERFIELD_VALID:
                $linkItem->http_code = self::BLC_VALID_FIELD_HTTP_CODE;
                $linkItem->broken = self::BLC_BROKEN_FALSE;
                break;

            case self::BLCCHECKERFIELD_UNKOWN:
                break;
        }
    }
    private function checkSqlField(string $fieldId, array $selectedValues): int
    {
        $fieldParams = $this->getFieldParamsBy($fieldId);
        $params = new Registry($fieldParams);
        $query = $params->get('query');
        $db = $this->getDatabase();
        $db->setQuery($query);
        $results = $db->loadAssocList();
        $possibleValues = array_column($results, 'value');

        $isValid = $this->areAllValuesInOtherArray($selectedValues, $possibleValues);

        return $isValid ? self::BLCCHECKERFIELD_VALID : self::BLCCHECKERFIELD_INVALID;
    }

    private function areAllValuesInOtherArray(array $left, array $right): bool
    {
        return empty(array_diff($left, $right));
    }

    private function getFieldParamsBy(int $id)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->from('#__fields AS a');
        $query->select($db->quoteName(['a.fieldparams']));

        $query->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $id);

        $db->setQuery($query);

        return $db->loadResult();
    }
}
