<?
    // Klassendefinition
    class IPS2RSSFeedTagesschau extends IPSModule 
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
		$this->RegisterPropertyInteger("Timer_1", 10);
		$this->RegisterTimer("Timer_1", 0, 'IPS2RSSFeedTagesschau_GetDataUpdate($_IPS["TARGET"]);');

		// Status-Variablen anlegen		
		$this->RegisterVariableString("RSSFeed", "RSS-Feed Tagesschau", "~HTMLBox", 10);
		
        }
 	
	public function GetConfigurationForm() 
	{ 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Kommunikationfehler!");
				
		$arrayElements = array(); 
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IP", "caption" => "IP");
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "MAC", "caption" => "MAC");
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "Name", "caption" => "Name");
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "Location", "caption" => "Lokalisierung");
		$arrayElements[] = array("type" => "Label", "label" => "Aktualisierung");
		$arrayElements[] = array("type" => "IntervalBox", "name" => "Timer_1", "caption" => "sek");
		$arrayElements[] = array("type" => "Label", "label" => "_____________________________________________________________________________________________________");
		$arrayElements[] = array("type" => "Label", "label" => "Maximale Wartezeit Ping (50 - 1000)");
		$arrayElements[] = array("type" => "IntervalBox", "name" => "MaxWaitTime", "caption" => "ms");
		$arrayElements[] = array("type" => "Label", "label" => "Mehrfach-Ping nutzen");
		$arrayElements[] = array("type" => "CheckBox", "name" => "MultiplePing", "caption" => "Mehrfach-Ping"); 
		$arrayElements[] = array("type" => "Label", "label" => "Anzahl der Mehrfach-Ping (2 - 15)");
		$arrayElements[] = array("type" => "IntervalBox", "name" => "Tries", "caption" => "Versuche");
		$arrayElements[] = array("type" => "Label", "label" => "_____________________________________________________________________________________________________");
		$arrayElements[] = array("type" => "IntervalBox", "name" => "PortScanStart", "caption" => "Port");
		$arrayElements[] = array("type" => "IntervalBox", "name" => "PortScanEnd", "caption" => "Port");
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements)); 		 
 	}       
	   
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
            	// Diese Zeile nicht löschen
            	parent::ApplyChanges();
		$this->RegisterMessage($this->InstanceID, 10103);
		$this->SetStatus(102);
		
		SetValueInteger($this->GetIDForIdent("GUI"), 0);
		
		$MAC = $this->ReadPropertyString("MAC");
		if (filter_var($MAC, FILTER_VALIDATE_MAC)) {
			$this->EnableAction("WOL");
		}
		else {
			$this->DisableAction("WOL");
		}
		
		$IP = $this->ReadPropertyString("IP");
		$PortScanStart = $this->ReadPropertyInteger("PortScanStart");
		$PortScanEnd = $this->ReadPropertyInteger("PortScanEnd");
		$Name = $this->ReadPropertyString("Name");
		$Location = $this->ReadPropertyString("Location");
		$State = GetValueInteger($this->GetIDForIdent("State"));
	
		if ((filter_var($IP, FILTER_VALIDATE_IP)) AND ($PortScanStart < $PortScanEnd)) {
    			$this->SetSummary($IP);
			SetValueString($this->GetIDForIdent("IP"), $IP);
			SetValueString($this->GetIDForIdent("Name"), $Name);
			SetValueString($this->GetIDForIdent("Location"), $Location);
			$this->EnableAction("OpenPorts");
			$this->GetDataUpdate();
			$this->GUI();
			$this->SetTimerInterval("Timer_1", $this->ReadPropertyInteger("Timer_1") * 1000);
			$IP_Parts = explode(".", $IP);
			$Position = $IP_Parts[3] * 10;
			$Result = $this->SendDataToParent(json_encode(Array("DataID"=> "{31F3B680-3AD6-4C50-EC4B-ED0A21656029}", 
				"Function" => "SetInstance", "InstanceID" => $this->InstanceID, "Name" => $Name, "State" => $State, "Position" => $Position)));
		}
		else {
			$this->SetSummary("");
			$this->DisableAction("OpenPorts");
			$this->DisableAction("WOL");
			$this->SendDebug("ApplyChanges", "Keine gueltige IP verfügbar oder Scan-Ports unplausibel!", 0);
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
	
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
	        case "WOL":
	            	If ($Value == true) { 
				SetValueBoolean($this->GetIDForIdent("WOL"), true);
				$this->WakeOnLAN();
			}
	            break;
		case "OpenPorts":
	            	If ($Value == true) { 
				SetValueBoolean($this->GetIDForIdent("OpenPorts"), true);
				$this->OpenPorts();
			}
	            break;
	 
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}    
	    
	// Beginn der Funktionen
	public function GetDataUpdate()
	{
		$MultiplePing = $this->ReadPropertyBoolean("MultiplePing");
		If ($MultiplePing == false) {
			$Result = unserialize($this->Simple_Ping());
			If ($Result["Ping"] == true) {
				$Ping = 3;
				$SuccessRate = 100;
			}
			else {
				$Ping = 1;
				$SuccessRate = 0;
			}
			$MinDuration = $Result["Duration"];
			$AvgDuration = $Result["Duration"];
			$MaxDuration = $Result["Duration"];
		}
		else {
			$Result = unserialize($this->Multiple_Ping());
			$Ping = $Result["Ping"];
			$SuccessRate = $Result["SuccessRate"];
			$MinDuration = $Result["MinDuration"];
			$AvgDuration = $Result["AvgDuration"];
			$MaxDuration = $Result["MaxDuration"];
		}
		
		If ($Ping <> GetValueInteger($this->GetIDForIdent("State"))) {
			SetValueInteger($this->GetIDForIdent("State"), $Ping);
		}
		If ($SuccessRate <> GetValueInteger($this->GetIDForIdent("SuccessRate"))) {
			SetValueInteger($this->GetIDForIdent("SuccessRate"), $SuccessRate);
		}
		If ($MinDuration <> GetValueFloat($this->GetIDForIdent("MinDuration"))) {
			SetValueFloat($this->GetIDForIdent("MinDuration"), $MinDuration);
		}
		If ($AvgDuration <> GetValueFloat($this->GetIDForIdent("AvgDuration"))) {
			SetValueFloat($this->GetIDForIdent("AvgDuration"), $AvgDuration);
		}
		If ($MaxDuration <> GetValueFloat($this->GetIDForIdent("MaxDuration"))) {
			SetValueFloat($this->GetIDForIdent("MaxDuration"), $MaxDuration);
		}
		SetValueInteger($this->GetIDForIdent("LastUpdate"), time() );
		
		$this->GUI();
		
		$Result = $this->SendDataToParent(json_encode(Array("DataID"=> "{31F3B680-3AD6-4C50-EC4B-ED0A21656029}", 
				"Function" => "SetState", "InstanceID" => $this->InstanceID, "State" => $Ping)));
		
	}
	
	private function Simple_Ping()
	{
    		$this->SendDebug("Simple_Ping", "Ausfuehrung", 0);
		$IP = $this->ReadPropertyString("IP");
		$MaxWaitTime = $this->ReadPropertyInteger("MaxWaitTime");
		$MaxWaitTime = min(1000, max(50, $MaxWaitTime));
		$Result = array();
		$Start = microtime(true);
    		$Response = Sys_Ping($IP, $MaxWaitTime); 
    		$Duration = microtime(true) - $Start;
    		$Result["Ping"] = $Response;
    		$Result["Duration"] = round($Duration * 1000, 2);
		$this->SendDebug("Simple_Ping", "Dauer: ".$Duration. " Ergebnis: ".(boolval($Response)), 0);
		
	return serialize($Result);
	}    
	    
	private function Multiple_Ping()
	{
    		$this->SendDebug("Multiple_Ping", "Ausfuehrung", 0);
		$IP = $this->ReadPropertyString("IP");
		$MaxWaitTime = $this->ReadPropertyInteger("MaxWaitTime");
		$MaxWaitTime = min(1000, max(50, $MaxWaitTime));
		$Tries = $this->ReadPropertyInteger("Tries");
		$Tries = min(15, max(2, $Tries));
		$Result = array();
		$Ping = array();
		$Duration = array();
		
		for ($i = 0; $i < $Tries; $i++) {
			$Start = microtime(true);
			$Response = Sys_Ping($IP, $MaxWaitTime); 
			$Duration[] = microtime(true) - $Start;
			$Ping[] = $Response;
			
		}
		// Ping-Werte berechnen
		$MinDuration = round(min($Duration) * 1000, 2);
		$AvgDuration = round((array_sum($Duration)/count($Duration)) * 1000, 2);
		$MaxDuration = round(max($Duration) * 1000, 2);
		// Erfolg auswerten
		$SuccessRate = Round((array_sum($Ping)/count($Ping)) * 100, 2);
		$this->SendDebug("Multiple_Ping", "Min: ".$MinDuration."ms, Durchschnitt: ".$AvgDuration."ms, Max: ".$MaxDuration."ms, Erfolg: ".$SuccessRate."%"." Versuche: ".(count($Ping)), 0);
		If ($SuccessRate == 100) {
			$Result["Ping"] = 3;
		}
		elseif ($SuccessRate == 0) {
			$Result["Ping"] = 1;
		}
		else {
			$Result["Ping"] = 2;
		}
		$Result["SuccessRate"] = $SuccessRate;
		$Result["MinDuration"] = $MinDuration;
		$Result["AvgDuration"] = $AvgDuration;
		$Result["MaxDuration"] = $MaxDuration;
	return serialize($Result);
	}    
	
	private function WakeOnLAN()
	{
    		$this->SendDebug("WakeOnLAN", "Ausfuehrung", 0);
		$MAC = $this->ReadPropertyString("MAC");
		if (filter_var($MAC, FILTER_VALIDATE_MAC)) {
			$broadcast = "255.255.255.255";
			$mac_array = preg_split('#:#', $MAC);
			$hwaddr = '';
			foreach($mac_array AS $octet)
			{
				$hwaddr .= chr(hexdec($octet));
			}
			// Create Magic Packet
			$packet = '';
			for ($i = 1; $i <= 6; $i++)
			{
				$packet .= chr(255);
			}
			for ($i = 1; $i <= 16; $i++)
			{
				$packet .= $hwaddr;
			}
			$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			if ($sock)
			{
				$options = socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, true);
				if ($options >=0) 
				{    
					$e = socket_sendto($sock, $packet, strlen($packet), 0, $broadcast, 7);
					socket_close($sock);
				}    
			}
		}
		else {
			$this->SendDebug("WakeOnLAN", "Keine gueltige MAC verfügbar!", 0);
		}
		SetValueBoolean($this->GetIDForIdent("WOL"), false);
	}  
	    
	private function GUI() 
	{
		$this->SendDebug("GUI", "Ausfuehrung", 0);
		$IP = $this->ReadPropertyString("IP");
		$Result = false; 
		
		if (filter_var($IP, FILTER_VALIDATE_IP)) {
			$fp = @fsockopen($IP, 80, $errno, $errstr, 0.2);
			if (!$fp) {
				$GUI80 = false;
			} else {
				fclose($fp);
				$GUI80 = true;
			}
			
			$fp = @fsockopen($IP, 433, $errno, $errstr, 0.2);
			if (!$fp) {
				$GUI433 = false;
			} else {
				fclose($fp);
				$GUI433 = true;
			}
			If (($GUI80 == true) OR ($GUI433 == true)) {
				If (GetValueInteger($this->GetIDForIdent("GUI")) <> 2) {
					SetValueInteger($this->GetIDForIdent("GUI"), 2);
					$DeviceURL = '<a href='."http://".$IP.' target="_blank">'.$IP.'</a>';
					SetValueString($this->GetIDForIdent("IP"), $DeviceURL);
				}
				$Result = true; 
			}
			else {
				If (GetValueInteger($this->GetIDForIdent("GUI")) <> 1) {
					SetValueInteger($this->GetIDForIdent("GUI"), 1);
					SetValueString($this->GetIDForIdent("IP"), $IP);
				}
			}
		}
	return $Result;
	}   
	
	private function OpenPorts() 
	{
		$this->SendDebug("OpenPorts", "Ausfuehrung", 0);
		$IP = $this->ReadPropertyString("IP");
		$PortScanStart = $this->ReadPropertyInteger("PortScanStart");
		$PortScanEnd = $this->ReadPropertyInteger("PortScanEnd");
		SetValueString($this->GetIDForIdent("OpenPortsResult"), "Scan gestartet: ".date("d.m.Y H:i:s")." ".chr(13));
		$OpenPorts = array();
		if (filter_var($IP, FILTER_VALIDATE_IP)) {
			for ($i = $PortScanStart; $i < $PortScanEnd; $i++) {
				$fp = @fsockopen($IP, $i, $errno, $errstr, 0.1);
				if (!$fp) {
					// keine Aktion
				} else {
					fclose($fp);
					$this->SendDebug("OpenPorts", "Offener Port: .$i", 0);
					SetValueString($this->GetIDForIdent("OpenPortsResult"), GetValueString($this->GetIDForIdent("OpenPortsResult")).str_pad($i, 5, '0', STR_PAD_LEFT).chr(9).getservbyport($i, "tcp").chr(13));
					$OpenPorts[$i] = "unbekannt";
				}
			}
		}
		SetValueString($this->GetIDForIdent("OpenPortsResult"), GetValueString($this->GetIDForIdent("OpenPortsResult"))."Scan Beendet");
		SetValueBoolean($this->GetIDForIdent("OpenPorts"), false);
	return serialize($OpenPorts);
	}   
	    
	private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 1);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 1)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);        
	}    
	    
	private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 2);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 2)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
	        IPS_SetVariableProfileDigits($Name, $Digits);
	}
}
?>
