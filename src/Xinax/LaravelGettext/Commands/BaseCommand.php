<?php

namespace Xinax\LaravelGettext\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Xinax\LaravelGettext\Config\ConfigManager;
use Xinax\LaravelGettext\Exceptions\LocaleFileNotFoundException;

class BaseCommand extends Command{

	/**
	 * Create a new command instance.
	 * @return void
	 */
	public function __construct(){
		
		$configManager = new ConfigManager;
		$this->configuration = $configManager->get();

		parent::__construct();
	}

	/**
	 * Constructs and returns the full path to 
	 * translaition files 
	 * @param String $append
	 * @return String
	 */	
	protected function getDomainPath($append=null){
		
		$path = array(
			app_path(),
			$this->configuration->getTranslationsPath(),
			"i18n"
		);

		if(!is_null($append)){
			array_push($path, $append);
		}

		return implode(DIRECTORY_SEPARATOR, $path);

	}  

	/**
     * Creates a configured .po file on $path. If write is true the file will 
     * be created, otherwise the file contents are returned.
     * @param String $path
     * @param String $locale     
     * @param Boolean $write     
     * @return Integer | String
     */
    protected function createPOFile($path, $locale, $write=true){

    	$project = $this->configuration->getProject();
    	$timestamp = date("Y-m-d H:iO");
    	$translator = $this->configuration->getTranslator();
    	$encoding = $this->configuration->getEncoding();

    	$template = 'msgid ""'."\n";
		$template .= 'msgstr ""'."\n";
		$template .= '"Project-Id-Version: '.$project.'\n'."\"\n";
		$template .= '"POT-Creation-Date: '.$timestamp.'\n'."\"\n";
		$template .= '"PO-Revision-Date: '.$timestamp.'\n'."\"\n";
		$template .= '"Last-Translator: '.$translator.'\n'."\"\n";
		$template .= '"Language-Team: '.$translator.'\n'."\"\n";
		$template .= '"Language: '.$locale.'\n'."\"\n";
		$template .= '"MIME-Version: 1.0'.'\n'."\"\n";
		$template .= '"Content-Type: text/plain; charset='.$encoding.'\n'."\"\n";
		$template .= '"Content-Transfer-Encoding: 8bit'.'\n'."\"\n";
		$template .= '"X-Generator: Poedit 1.5.4'.'\n'."\"\n";
		$template .= '"X-Poedit-KeywordsList: _'.'\n'."\"\n";
		$template .= '"X-Poedit-Basepath:'.app_path().'\n'."\"\n";
		$template .= '"X-Poedit-SourceCharset: '.$encoding.'\n'."\"\n";

		// Source paths
		$sourcePaths = $this->configuration->getSourcePaths();

		$i = 0;
		foreach ($sourcePaths as $sourcePath) {
			$template .= '"X-Poedit-SearchPath-'.$i.': '.$sourcePath.'\n'."\"\n";	
			$i++;
		}
		
		if($write){

			// File creation
			$file = fopen($path, "w");
			$result = fwrite($file, $template);
			fclose($file);
			return $result;	

		} else {

			// Contents for update
			return $template . "\n";
		}
		

    }	

    /**
     * Adds a new locale directory + .po file
     * @param String $localePath
     * @param String $locale
     */
    protected function addLocale($localePath, $locale){

    	if(!@mkdir($localePath)){
			throw new FileCreationException(
				"I can't create the directory: $localePath");
		}

		$localeGettext = $localePath . 
				DIRECTORY_SEPARATOR . 
				"LC_MESSAGES";

		if(!@mkdir($localeGettext)){
			throw new FileCreationException(
				"I can't create the directory: $localeGettext");
		}		    		

		$poPath = $localeGettext . 
			  	DIRECTORY_SEPARATOR . 
			 	$this->configuration->getDomain() . 
			 	".po";

		if(!$this->createPOFile($poPath, $locale)){
			throw new FileCreationException(
				"I can't create the file: $poPath");	
		}

    }

    /**
     * Update the .po file headers (mainly source-file paths)
     * @param String $localePath
     * @param String $locale
     */
    protected function updateLocale($localePath, $locale){
    	
    	$localePOPath = implode(array(
    		$localePath,
    		"LC_MESSAGES",
    		$this->configuration->getDomain() . ".po",
		), DIRECTORY_SEPARATOR);

		if(!file_exists($localePOPath) ||
			!$localeContents = file_get_contents($localePOPath)){
			throw new LocaleFileNotFoundException(
				"I can't read $localePOPath verify your locale structure");
		}

		$newHeader = $this->createPOFile($localePath, $locale, false);

		// Header replacement
		$localeContents = preg_replace('/^([^#])+:?/', $newHeader, $localeContents);

		if(!file_put_contents($localePOPath, $localeContents)){
			throw new LocaleFileNotFoundException("I can't write on $localePOPath");
		}

		$this->comment("PO file for locale: $locale were updated successfuly");

    }

}