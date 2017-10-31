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
 * @class LanguageData
 * @brief Stores language data in a class object
 *
 * This class stores data of the Language object. These are the paths to all
 * relevant language files, the configured language and the default language.
 * This object is designed to be stored in a PHP session. The Language
 * object itself couldn't be stored in a Session because it uses PHP objects
 * which couldn't stored in a PHP session.
 * @par Examples
 * @code // show how to use this class with the language class and sessions
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
 * $language->addLanguageData($session->getObject('languageData')); @endcode
 */
class LanguageData
{
    const REFERENCE_LANGUAGE = 'en'; // The ISO code of the default language that should be read if in the current language the text id is not translated

    /**
     * @var array<int,string> Array with all relevant language files
     */
    private $languageFilePath = array();
    /**
     * @var string The ISO code of the language that should be read in this object
     */
    private $language = '';
    /**
     * @var array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    private $countries = array();
    /**
     * @var array<string,string> Stores all read text data in an array to get quick access if a text is required several times
     */
    private $textCache = array();

    /**
     * Creates an object that stores all necessary language data and can be handled in session.
     * Therefore the language must be set and optional a path where the language files are stored.
     * @param string $language     The ISO code of the language for which the texts should be read e.g. @b 'de'
     *                             If no language is set than the browser language will be determined.
     * @param string $languagePath Optional a server path to the language files. If no path is set
     *                             than the default Admidio language path @b adm_program/languages will be set.
     */
    public function __construct(string $language = '', string $languagePath = '')
    {
        if ($languagePath === '')
        {
            $this->addLanguagePath(ADMIDIO_PATH . FOLDER_LANGUAGES);
        }
        else
        {
            $this->addLanguagePath($languagePath);
        }

        if ($language === '')
        {
            // get browser language and set this language as default
            $language = static::determineBrowserLanguage(self::REFERENCE_LANGUAGE);
        }

        $this->setLanguage($language);
    }

    /**
     * Adds a new path of language files to the array with all language paths
     * where Admidio should search for language files.
     * @param string $path Server path where Admidio should search for language files.
     */
    public function addLanguagePath(string $path)
    {
        if ($path !== '' && !in_array($path, $this->languageFilePath, true))
        {
            $this->languageFilePath[] = $path;
        }
    }

    /**
     * Determine the language from the browser preferences of the user.
     * @param string $defaultLanguage This language will be set if no browser language could be determined
     * @return string Return the preferred language code of the client browser
     */
    public static function determineBrowserLanguage(string $defaultLanguage): string
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
        {
            return $defaultLanguage;
        }

        $languages = preg_split('/\s*,\s*/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $languageChoosed = $defaultLanguage;
        $priorityChoosed = 0;

        foreach ($languages as $value)
        {
            if (!preg_match('/^([a-z]{2,3}(?:-[a-zA-Z]{2,3})?|\*)(?:\s*;\s*q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?))?$/', $value, $matches))
            {
                continue;
            }

            $langCodes = explode('-', $matches[1]);

            $priority = 1.0;
            if (isset($matches[2]))
            {
                $priority = (float) $matches[2];
            }

            if ($priorityChoosed < $priority && $langCodes[0] !== '*')
            {
                $languageChoosed = $langCodes[0];
                $priorityChoosed = $priority;
            }
        }

        return $languageChoosed;
    }

    /**
     * Returns an array with all countries and their ISO codes
     * @return array<string,string> Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function getCountriesArray(): array
    {
        return $this->countries;
    }

    /**
     * Returns the language code of the language of this object. This is the code that is set within
     * Admidio with some specials like de_sie. If you only want the ISO code then call getLanguageIsoCode().
     * @param bool $referenceLanguage If set to @b true than the language code of the reference language will returned.
     * @return string Returns the language code of the language of this object or the reference language.
     */
    public function getLanguage(bool $referenceLanguage = false): string
    {
        global $gLogger;

        if ($referenceLanguage)
        {
            $gLogger->warning('DEPRECATED: "$languageData->getLanguage(true)" is deprecated, use "LanguageData::REFERENCE_LANGUAGE" instead!');

            return self::REFERENCE_LANGUAGE;
        }

        return $this->language;
    }

    /**
     * Returns an array with all language paths that were set.
     * @return array<int,string> with all language paths that were set.
     */
    public function getLanguagePaths(): array
    {
        return $this->languageFilePath;
    }

    /**
     * @param string $textId Unique text id of the text that should be read e.g. SYS_COMMON
     * @return string Returns the cached text or empty string if text id isn't found
     */
    public function getTextCache(string $textId): string
    {
        if (array_key_exists($textId, $this->textCache))
        {
            return $this->textCache[$textId];
        }

        return '';
    }

    /**
     * Save the array with all countries and their ISO codes in an internal parameter for later use
     * @param array<string,string> $countries Array with all countries and their ISO codes e.g.: array('DEU' => 'Germany' ...)
     */
    public function setCountriesArray(array $countries)
    {
        $this->countries = $countries;
    }

    /**
     * Set a language to this object. If there was a language before than initialize the cache
     * @param string $language ISO code of the language that should be set to this object.
     */
    public function setLanguage(string $language)
    {
        if ($language !== $this->language)
        {
            // initialize all parameters
            $this->textCache = array();
            $this->countries = array();

            $this->language = $language;
        }
    }

    /**
     * Sets a new text into the text-cache
     * @param string $textId Unique text id where to set the text e.g. SYS_COMMON
     * @param string $text   The text to cache
     */
    public function setTextCache(string $textId, string $text)
    {
        $this->textCache[$textId] = $text;
    }
}
