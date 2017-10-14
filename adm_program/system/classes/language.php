<?php
declare(strict_types=1);
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Language
 * @brief Reads language specific texts that are identified with text ids out of language xml files
 *
 * The class will read a language specific text that is identified with their
 * text id out of an language xml file. The access will be manages with the
 * \SimpleXMLElement which search through xml files. An object of this class
 * can't be stored in a PHP session because it creates PHP core objects which
 * couldn't be stored in sessions. Therefore an object of @b LanguageData
 * should be assigned to this class that stored all necessary data and can be
 * stored in a session.
 * @par Examples
 * @code // show how to use this class with the language data class and sessions
 * script_a.php
 * // create a language data object and assign it to the language object
 * $language = new Language();
 * $languageData = new LanguageData('de');
 * $language->addLanguageData($languageData);
 * $session->addObject('languageData', $languageData);
 *
 * script_b.php
 * // read language data from session and add it to language object
 * $language = new Language();
 * $language->addLanguageData($session->getObject('languageData'));
 *
 * // read and display a language specific text with placeholders for individual content
 * echo $gL10n->get('MAI_EMAIL_SEND_TO_ROLE_ACTIVE', 'John Doe', 'Demo-Organization', 'Administrator');@endcode
 */
class Language
{
    /**
     * @var LanguageData An object of the class @b LanguageData that stores all necessary language data in a session
     */
    private $languageData;
    /**
     * @var array<string,string> An Array with all available languages and their ISO codes
     */
    private $languages = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the language from all paths that are set in @b $languageData.
     */
    private $xmlLanguageObjects = array();
    /**
     * @var array<string,\SimpleXMLElement> An array with all \SimpleXMLElement object of the reference language from all paths that are set in @b $languageData.
     */
    private $xmlRefLanguageObjects = array();

    /**
     * Language constructor.
     * @param LanguageData $languageDataObject An object of the class @b LanguageData.
     */
    public function __construct(LanguageData $languageDataObject = null)
    {
        $this->languageData =& $languageDataObject;
    }

    /**
     * Adds a language data object to this class. The object contains all necessary
     * language data that is stored in the PHP session.
     * @param LanguageData $languageDataObject An object of the class @b LanguageData.
     */
    public function addLanguageData(LanguageData $languageDataObject)
    {
        $this->languageData =& $languageDataObject;
    }

    /**
     * Adds a new path of language files to the array with all language paths where Admidio
     * should search for language files.
     * @param string $path Server path where Admidio should search for language files.
     */
    public function addLanguagePath($path)
    {
        $this->languageData->addLanguagePath($path);
    }

    /**
     * @param array<string,\SimpleXMLElement> $xmlLanguageObjects SimpleXMLElement array of each language path is stored
     * @param string                          $language           Language code
     * @param string                          $textId             Unique text id of the text that should be read e.g. SYS_COMMON
     * @return string Returns the text string of the text id or empty string if not found.
     */
    private function searchTextIdInLangObject(array $xmlLanguageObjects, $language, $textId)
    {
        foreach ($this->languageData->getLanguagePaths() as $languagePath)
        {
            $text = $this->searchLanguageText($xmlLanguageObjects, $languagePath, $language, $textId);

            if ($text !== '')
            {
                return $text;
            }
        }

        return '';
    }

    /**
     * Reads a text string out of a language xml file that is identified with a unique text id e.g. SYS_COMMON.
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @return string Returns the text string of the text id or empty string if not found.
     */
    private function getTextFromTextId($textId)
    {
        // first search text id in text-cache
        $text = $this->languageData->getTextCache($textId);

        // if text id wasn't found than search for it in language
        if ($text === '')
        {
            // search for text id in every \SimpleXMLElement (language file) of the object array
            $text = $this->searchTextIdInLangObject($this->xmlLanguageObjects, $this->languageData->getLanguage(), $textId);
        }

        // if text id wasn't found than search for it in reference language
        if ($text === '')
        {
            // search for text id in every \SimpleXMLElement (language file) of the object array
            $text = $this->searchTextIdInLangObject($this->xmlRefLanguageObjects, LanguageData::REFERENCE_LANGUAGE, $textId);
        }

        return $text;
    }

    /**
     * Reads a text string out of a language xml file that is identified
     * with a unique text id e.g. SYS_COMMON. If the text contains placeholders
     * than you must set more parameters to replace them.
     * @param string            $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @param array<int,string> $params Optional parameter for language string of translation id
     *
     * param  string $param1,$param2... The function accepts an undefined number of values which will be used
     *                                  to replace the placeholder in the text.
     *                                  $param1 will replace @b #VAR1# or @b #VAR1_BOLD#,
     *                                  $param2 will replace @b #VAR2# or @b #VAR2_BOLD# etc.
     * @return string Returns the text string with replaced placeholders of the text id.
     * @par Examples
     * @code // display a text without placeholders
     *                echo $gL10n->get('SYS_NUMBER');
     *
     * // display a text with placeholders for individual content
     * echo $gL10n->get('MAI_EMAIL_SEND_TO_ROLE_ACTIVE', ['John Doe', 'Demo-Organization', 'Administrator']);
     * @endcode
     */
    public function get($textId, $params = array())
    {
        global $gLogger;

        if (!$this->languageData instanceof LanguageData)
        {
            $gLogger->error('$this->languageData is not an instance of LanguageData!', array('languageData' => $this->languageData));

            return 'Error: $this->languageData is not an instance of LanguageData!';
        }

        $text = $this->getTextFromTextId($textId);

        // no text found then write #undefined text#
        if ($text === '')
        {
            return '#' . $textId . '#';
        }

        // replace placeholder with value of parameters

        if (is_array($params))
        {
            array_unshift($params, null);
            $paramsCount = count($params);
            $paramsArray = $params;
        }
        else
        {
            // TODO deprecated: Remove in Admidio 4.0
            $paramsCount = func_num_args();
            $paramsArray = func_get_args();

            $gLogger->warning(
                'DEPRECATED: "$gL10n->get(\'XXX\', 1, 2)" is deprecated, use "$gL10n->get(\'XXX\', array(1, 2))" instead!',
                array('textId' => $textId, 'params' => $params, 'paramsArray' => $paramsArray)
            );
        }

        for ($paramNumber = 1; $paramNumber < $paramsCount; ++$paramNumber)
        {
            $replaceArray = array(
                '#VAR' . $paramNumber . '#'      => $paramsArray[$paramNumber],
                '#VAR' . $paramNumber . '_BOLD#' => '<strong>' . $paramsArray[$paramNumber] . '</strong>'
            );
            $text = str_replace(array_keys($replaceArray), array_values($replaceArray), $text);
        }

        // replace square brackets with html tags
        $text = strtr($text, '[]', '<>');

        return $text;
    }

    /**
     * Returns an array with all countries and their ISO codes
     * @return array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function getCountries()
    {
        $countries = $this->languageData->getCountriesArray();

        if (count($countries) > 0)
        {
            return $countries;
        }

        $langFile    = ADMIDIO_PATH . FOLDER_LANGUAGES . '/countries_' . $this->languageData->getLanguage() . '.xml';
        $langFileRef = ADMIDIO_PATH . FOLDER_LANGUAGES . '/countries_' . LanguageData::REFERENCE_LANGUAGE   . '.xml';
        if (is_file($langFile))
        {
            $file = $langFile;
        }
        elseif (is_file($langFileRef))
        {
            $file = $langFileRef;
        }
        else
        {
            return array();
        }

        // read all countries from xml file
        $countriesXml = new \SimpleXMLElement($file, null, true);

        foreach ($countriesXml->children() as $stringNode)
        {
            $attributes = $stringNode->attributes();
            $countries[(string) $attributes->name] = (string) $stringNode;
        }

        asort($countries, SORT_LOCALE_STRING);
        $this->languageData->setCountriesArray($countries);

        return $this->languageData->getCountriesArray();
    }

    /**
     * Returns the name of the country in the language of this object. The country will be
     * identified by the ISO code e.g. 'DEU' or 'GBR' ...
     * @param string $isoCode The three digits ISO code of the country where the name should be returned.
     * @return string Return the name of the country in the language of this object.
     */
    public function getCountryByCode($isoCode)
    {
        if($isoCode === '')
        {
            return '';
        }

        $countries = $this->languageData->getCountriesArray();

        if(count($countries) === 0)
        {
            $countries = $this->getCountries();
        }
        return $countries[$isoCode];
    }

    /**
     * Returns the three digits ISO code of the country. The country will be identified
     * by the name in the language of this object
     * @param string $country The name of the country in the language of this object.
     * @return string|false Return the three digits ISO code of the country or false if country not found.
     */
    public function getCountryByName($country)
    {
        $countries = $this->languageData->getCountriesArray();

        if(count($countries) === 0)
        {
            $countries = $this->getCountries();
        }
        return array_search($country, $countries, true);
    }

    /**
     * Returns the ISO code of the language of this object.
     * @param bool $referenceLanguage If set to @b true than the ISO code of the reference language will returned.
     * @return string Returns the ISO code of the language of this object or the reference language e.g. @b de or @b en.
     */
    public function getLanguageIsoCode($referenceLanguage = false)
    {
        global $gLogger;

        if ($referenceLanguage)
        {
            $gLogger->warning('DEPRECATED: "$language->getLanguageIsoCode(true)" is deprecated, use "LanguageData::REFERENCE_LANGUAGE" instead!');

            return LanguageData::REFERENCE_LANGUAGE;
        }

        $language = $this->languageData->getLanguage();

        if($language === 'de_sie')
        {
            return 'de';
        }

        return $language;
    }

    /**
     * Returns the language code of the language of this object. This is the code that is set within
     * Admidio with some specials like de_sie. If you only want the ISO code then call getLanguageIsoCode().
     * @param bool $referenceLanguage If set to @b true than the language code of the reference language will returned.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage($referenceLanguage = false)
    {
        global $gLogger;

        if ($referenceLanguage)
        {
            $gLogger->warning('DEPRECATED: "$language->getLanguage(true)" is deprecated, use "LanguageData::REFERENCE_LANGUAGE" instead!');

            return LanguageData::REFERENCE_LANGUAGE;
        }

        return $this->languageData->getLanguage();
    }

    /**
     * Creates an array with all languages that are possible in Admidio.
     * The array will have the following syntax e.g.: array('DE' => 'deutsch' ...)
     * @return array<string,string> Return an array with all available languages.
     */
    public function getAvailableLanguages()
    {
        if(count($this->languages) === 0)
        {
            $languagesXml = new \SimpleXMLElement(ADMIDIO_PATH . FOLDER_LANGUAGES . '/languages.xml', null, true);

            foreach($languagesXml->children() as $stringNode)
            {
                $attributes = $stringNode->children();
                $this->languages[(string) $attributes->isocode] = (string) $attributes->name;
            }
        }

        return $this->languages;
    }

    /**
     * Search for text id in a language xml file and return the text. If no text was found than nothing is returned.
     * @param array<string,\SimpleXMLElement> $objectArray  The reference to an array where every SimpleXMLElement of each language path is stored
     * @param string                          $languagePath The path in which the different language xml files are.
     * @param string                          $language     The ISO code of the language in which the text will be searched
     * @param string                          $textId       The id of the text that will be searched in the file.
     * @return string Return the text in the language or nothing if text id wasn't found.
     */
    public function searchLanguageText(array &$objectArray, $languagePath, $language, $textId)
    {
        // if not exists create a \SimpleXMLElement of the language file in the language path
        // and add it to the array of language objects
        if(!array_key_exists($languagePath, $objectArray))
        {
            $languageFile = $languagePath.'/'.$language.'.xml';

            if(is_file($languageFile))
            {
                $objectArray[$languagePath] = new \SimpleXMLElement($languageFile, null, true);
            }
        }

        if($objectArray[$languagePath] instanceof \SimpleXMLElement)
        {
            // text not in cache -> read from xml file in "Android Resource String" format
            $node = $objectArray[$languagePath]->xpath('/resources/string[@name="'.$textId.'"]');

            if($node == false)
            {
                // fallback for old Admidio language format prior to version 3.1
                $node = $objectArray[$languagePath]->xpath('/language/version/text[@id="'.$textId.'"]');
            }

            if($node != false)
            {
                // set line break with html
                // Within Android string resource all apostrophe are escaped so we must remove the escape char
                // replace highly comma, so there are no problems in the code later
                $text = str_replace(array('\\n', '\\\'', '\''), array('<br />', '\'', '&rsquo;'), $node[0]);
                $this->languageData->setTextCache($textId, $text);

                return $text;
            }
        }

        return '';
    }

    /**
     * Set a language to this object. If there was a language before than initialize the cache
     * @param string $language ISO code of the language that should be set to this object.
     */
    public function setLanguage($language)
    {
        if($language !== $this->languageData->getLanguage())
        {
            // initialize data
            $this->xmlLanguageObjects    = array();
            $this->xmlRefLanguageObjects = array();

            $this->languageData->setLanguage($language);
        }
    }
}
