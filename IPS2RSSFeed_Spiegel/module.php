<?
    // Klassendefinition
    class IPS2RSSFeed_Spiegel extends IPSModule 
    {
	public function Destroy() 
	{
		//Never delete this line!
		parent::Destroy();
		$this->SetTimerInterval("Timer_1", 0);
	}
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->RegisterPropertyBoolean("Active", false);
		$this->RegisterPropertyInteger("MessageCount", 2);
		$this->RegisterPropertyInteger("Timer_1", 10);
		$this->RegisterTimer("Timer_1", 0, 'IPS2RSSFeedSpiegel_GetDataUpdate($_IPS["TARGET"]);');

		// Status-Variablen anlegen		
		$this->RegisterVariableString("RSSFeed", "RSS-Feed Spiegel", "~HTMLBox", 10);
		
        }
 	
	public function GetConfigurationForm() 
	{ 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Kommunikationfehler!");
				
		$arrayElements = array(); 
		$arrayElements[] = array("type" => "CheckBox", "name" => "Active", "caption" => "Aktiv");
		$arrayElements[] = array("type" => "Label", "label" => "Anzahl der anzuzeigenden Nachrichten");
		$arrayElements[] = array("type" => "IntervalBox", "name" => "MessageCount", "caption" => "Anzahl");
		$arrayElements[] = array("type" => "Label", "label" => "Aktualisierung");
		$arrayElements[] = array("type" => "IntervalBox", "name" => "Timer_1", "caption" => "min");
		$arrayElements[] = array("type" => "Label", "label" => "_____________________________________________________________________________________________________");
		
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements)); 		 
 	}       
	   
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
            	// Diese Zeile nicht löschen
            	parent::ApplyChanges();
		$this->SetStatus(102);
		$this->RegisterMessage($this->InstanceID, 10103);
	
		if ($this->ReadPropertyBoolean("Active") == true) {
			$this->GetDataUpdate();
			$this->SetTimerInterval("Timer_1", $this->ReadPropertyInteger("Timer_1") * 60 * 1000);
		}
		else {
			$this->SetTimerInterval("Timer_1", 0);
		}
	}
	
	public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    	{
 		switch ($Message) {
			case 10103:
				$this->ApplyChanges();
				break;
			
		}
    	}
	    
	// Beginn der Funktionen
	public function GetDataUpdate()
	{
		if ($this->ReadPropertyBoolean("Active") == true) {
			$this->SendDebug("GetDataUpdate", "Ausfuehrung", 0);
			$MessageCount = $this->ReadPropertyInteger("MessageCount");
			// Feed einlesen
			if( !$xml = simplexml_load_file('http://www.tagesschau.de/xml/rss2') ) {
    				$this->SendDebug("GetDataUpdate", "Fehler beim Einlesen der XML Datei!", 0);
				return;
			}

			// Ausgabe Array
			$out = array();

			// auszulesende Datensaetze
			$i = $MessageCount;

			// Items vorhanden?
			if( !isset($xml->channel[0]->item) ) {
				$this->SendDebug("GetDataUpdate", "Keine Items vorhanden!", 0);
				return;
			}

			// Items holen
			foreach($xml->channel[0]->item as $item) {
				if( $i-- == 0 ) {
					break;
				}

				$out[] = array(
					'title'        => (string) $item->title,
					'description'  => (string) $item->description,
					'link'         => (string) $item->guid,
					'date'         => date('d.m.Y H:i', strtotime((string) $item->pubDate)),
        				'encoded_content'      => (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded
				);
			}

			$Result = "";
			// Eintraege ausgeben
			foreach ($out as $value) {
				$Title = '<h3>'.$value['title'].'</h3>';
				$Result = $Result.$Title.$value['encoded_content']."\r\n";
			}
			If ($Result <> GetValueString($this->GetIDForIdent("RSSFeed"))) {
				SetValueString($this->GetIDForIdent("RSSFeed"), $Result);
			}
		}
	}
	    
}
?>