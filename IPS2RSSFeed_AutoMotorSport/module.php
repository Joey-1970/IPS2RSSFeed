<?
    // Klassendefinition
    class IPS2RSSFeed_AutoMotorSport extends IPSModule 
    {
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->RegisterPropertyBoolean("Active", false);
		$this->RegisterPropertyInteger("MessageCount", 2);
		$this->RegisterPropertyInteger("Timer_1", 10);
		$this->RegisterPropertyInteger("Width", 700);
		$this->RegisterTimer("Timer_1", 0, 'IPS2RSSFeedAutoMotorSport_GetDataUpdate($_IPS["TARGET"]);');

		// Status-Variablen anlegen		
		$this->RegisterVariableString("RSSFeed", "RSS-Feed AutoMotorSport Oldtimer", "~HTMLBox", 10);
		
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
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "MessageCount", "caption" => "Anzahl der anzuzeigenden Nachrichten", "minimum" => 1, "maximum" => 20);
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "Width",  "caption" => "Tabellenbreite", "minimum" => 400, "maximum" => 1000, "suffix" => "px");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "Timer_1",  "caption" => "Aktualisierung", "minimum" => 1, "maximum" => 600, "suffix" => "min");
		$arrayElements[] = array("type" => "Label", "label" => "_____________________________________________________________________________________________________");
		
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements)); 		 
 	}       
	   
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
            	// Diese Zeile nicht löschen
            	parent::ApplyChanges();
	
		if ($this->ReadPropertyBoolean("Active") == true) {
			If ($this->GetStatus() <> 102) {
				$this->SetStatus(102);
			}
			$this->GetDataUpdate();
			$this->SetTimerInterval("Timer_1", $this->ReadPropertyInteger("Timer_1") * 60 * 1000);
		}
		else {
			If ($this->GetStatus() <> 104) {
				$this->SetStatus(104);
			}
			$this->SetTimerInterval("Timer_1", 0);
		}
	}
	
	// Beginn der Funktionen
	public function GetDataUpdate()
	{
		if ($this->ReadPropertyBoolean("Active") == true) {
			$this->SendDebug("GetDataUpdate", "Ausfuehrung", 0);
			$MessageCount = $this->ReadPropertyInteger("MessageCount");
			// Feed einlesen
			if( !$xml = simplexml_load_file('https://www.auto-motor-und-sport.de/rss/oldtimer') ) {
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
        				'image'        => (string) $item->enclosure['url']				);
			}

			$Width = $this->ReadPropertyInteger("Width");
			// Eintraege ausgeben
			$HTML = '<style type="text/css">';
			$HTML .= '<link rel="stylesheet" href="./.../webfront.css">';
			$HTML .= "</style>";
			$HTML .= '<table class="tg">';
			foreach ($out as $value) {
     				$HTML .= '<tr>';
     					$HTML .= '<td class="tg-611x" width='.$Width.' ><h3>'.$value['title'].'</h3></td>';
    				$HTML .= '</tr>';
     				
				$HTML .= '<tr>';
    					$Image = '<img src='.$value['image'].' style="width:'.$Width.'px;">';
    					$HTML .= '<td class="tg-611x">'.$Image.'</td>';
    				$HTML .= '</tr>';

     				$HTML .= '<tr>';
    					$HTML .= '<td class="tg-611x" width='.$Width.';word-break:break-word; >'.$value['description'].'</td>';
    				$HTML .= '</tr>';

     				$HTML .= '<tr>';
    					$HTML .= '<td class="tg-611x" height=25; ></td>';
    				$HTML .= '</tr>';
			}
			$HTML .= '</table>';
			If ($HTML <> $this->GetValue("RSSFeed")) {
				$this->SetValue("RSSFeed", $HTML);
			}
		}
	}
	    
}
?>
