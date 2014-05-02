<?php

/*
__PocketMine Plugin__ <--- Maybe this is the coolest tag I think
name=Plague
version=1.1
author=onebone
apiversion=13
class=Plague
*/

/*
===============
CHANGE LOG
===============
v1.0 : Initial release
v1.1 : Something..
*/

define("ROUTE_WATER", 0b00);
define("ROUTE_AIR", 0b01);
define("ROUTE_TOUCH", 0b10);

define("POSSIBILITY_LOW", 10000);
define("POSSIBILITY_MEDIUM", 8000);
define("POSSIBILITY_HIGH", 3000);
define("POSSIBILITY_VERY_HIGH", 800);

define("DANGER_LOW", 10);
define("DANGER_MEDIUM", 20);
define("DANGER_HIGH", 30);

define("CURRENT_PLAGUE_VERSION", 2);

class Plague implements Plugin{
	private $api, $players, $config, $virus;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->virus = array();
		$this->players = array();
	}
	
	public function init(){
		@mkdir(DATA_PATH."plugins/Plague/");
		@mkdir(DATA_PATH."plugins/Plague/Virus"); // Where is mkdirs???
		require_all(DATA_PATH."plugins/Plague/Virus/");
		if(!is_file(DATA_PATH."plugins/Plague/players.dat")){
			file_put_contents(DATA_PATH."plugins/Plague/players.dat", serialize(array()));
		}
		if(!is_file(DATA_PATH."plugins/Plague/recoverySchedule.dat")){
			file_put_contents(DATA_PATH."plugins/Plague/recoverySchedule.dat", serialize(array()));
		}
		$this->createConfig();
		if($this->config->get("load-default-disease")){
			$temp = new CommonCold($this, ServerAPI::request());
			console("[INFO] Loading plague virus ".FORMAT_GREEN."\"Common Cold\"".FORMAT_RESET);
			$version = $temp->getPlagueVersion();
			if($version < CURRENT_PLAGUE_VERSION){
				console("[WARNING] Plague virus \"Common Cold\" may not be stable for this version of plague.");
			}
			$level = abs($temp->getDangerLevel()) & 0x7FFFFFFF;
			$tmp = $temp->getInfectionInfo();
			if(!is_array($tmp)) continue;
			$possibility = array();
			foreach($tmp as $r => $p){
				$possibility[$r] = abs($p) & 0x7FFFFFFF;
			}
			$this->virus[] = array(
				$temp,
				$possibility,
				$level
			);
		}
		$this->recoverySchedule = unserialize(file_get_contents(DATA_PATH."plugins/Plague/recoverySchedule.dat"));
		$this->players = unserialize(file_get_contents(DATA_PATH."plugins/Plague/players.dat"));
		foreach(scandir(DATA_PATH."plugins/Plague/Virus/") as $file){
			// if($file->substr($file->length() - 4)->equals(".php")) JAVA!
			if(substr($file, -4) == ".php"){
				$className = substr($file, 0, -4);
				$temp = new $className($this, ServerAPI::request());
				if($temp instanceof PlagueVirus){
					console("[INFO] Loading plague virus ".FORMAT_GREEN."\"$className\"".FORMAT_RESET);
					$version = $temp->getPlagueVersion();
					if($version < CURRENT_PLAGUE_VERSION){
						console("[WARNING] Plague virus \"$className\" may not be stable for this version of plague.");
					}
					$level = abs($temp->getDangerLevel()) & 0x7FFFFFFF;
					$tmp = $temp->getInfectionInfo();
					if(!is_array($tmp)) continue;
					$possibility = array();
					foreach($tmp as $r => $p){
						$possibility[$r] = abs($p) & 0x7FFFFFFF;
					}
					$this->virus[] = array(
						$temp,
						$possibility,
						$level
					);
				}
			}
		}
		$allVirus = array();
		foreach($this->virus as $virus){
			$allVirus[] = $virus[0]->getVirusName();
		}
		foreach($this->players as $iusername => $data){
			foreach($data as $key => $d){
				if(!in_array($d[0], $allVirus)){
					console("[INFO] Plague virus $d[0] has been deleted. Removing infected players...");
					$this->players[$iusername][$key] = null;
					unset($this->players[$iusername][$key]);
				}
			}
		}
		foreach($this->recoverySchedule as $recoverySchedule => $trash){
			$tick = rand(3600, 7200);
			$this->api->schedule($tick, array($this, "onRecoverSchedule"), $recoverySchedule);
		}
		$this->api->addHandler("player.move", array($this, "onMove"));
		$this->api->addHandler("player.connect", array($this, "onConnect"));
		$this->api->event("server.close", array($this, "onClose"));
	}
	
	public function onConnect($data){
		if(!isset($this->players[$data->iusername])){
			$this->players[$data->iusername] = array();
		}
	}
	
	public function onClose(){
		file_put_contents(DATA_PATH."plugins/Plague/players.dat", serialize($this->players));
		file_put_contents(DATA_PATH."plugins/Plague/recoverySchedule.dat", serialize($this->recoverySchedule));
	}
	
	public function onMove($data){
		if($data->player instanceof Player){
			foreach($this->virus as $v){
				if($this->checkInfected($data->player->iusername, $v[0])) continue;
				$key = ROUTE_TOUCH;
				$routeFlag = 0b00;
				if(array_key_exists(ROUTE_TOUCH, $v[1])){
					$touched = false;
					foreach($this->api->player->getAll() as $player){
						if($player->iusername == $data->player->iusername) continue;
						$px = $player->entity->x;
						$py = $player->entity->y;
						$pz = $player->entity->z;
						$x = $data->x;
						$y = $data->y;
						$z = $data->z;
						if(($px + 1 > $x and $px - 1 < $x) and ($py + 1 > $y and $py - 1) and ($pz + 1 > $z and $pz - 1 < $z)){
							$touched = true;
							break;
						}
					}
					if(!$touched){
						goto checkWater;
					}
					$routeFlag |= ROUTE_TOUCH;
				}
				checkWater:
				if(array_key_exists(ROUTE_WATER, $v[1])){
					if(!$data->level->getBlock($data) instanceof LiquidBlock){
						goto checkAir;
					}
					$key = ROUTE_WATER;
					$routeFlag |= ROUTE_WATER;
				}
				checkAir:
				if(array_key_exists(ROUTE_AIR, $v[1])){
					$key = ROUTE_AIR;
					$routeFlag |= ROUTE_AIR;
				}
				if(mt_rand(0, $v[1][$key]) == 0){
					$exist = false;
					$name = $v[0]->getVirusName();
					console("[DEBUG] Plague {$data->player->iusername} have been infected by the virus \"{$v[0]->getVirusName()}\"", true, true, 4);
					$v[0]->onInfect($data->player, $sendRoute);
					if(!isset($this->recoverySchedule[$data->player->iusername])){
						$this->recoverySchedule[$data->player->iusername] = true;
						$tick = rand(3600, 7200);
						$this->api->schedule($tick, array($this, "onRecoverSchedule"), $data->player->iusername);
					}
					$this->players[$data->player->iusername][] = array(
						$v[0]->getVirusName(),
						time()
					);
				}
			}
		}
	}
	
	public function findVirusByName($name){
		foreach($this->virus as $virus){
			if($virus[0]->getVirusName() == $name){
				return $virus[0];
			}
		}
		return false;
	}
	
	public function recoverMe($iusername, $virusName){
		$iusername = strtolower($iusername);
		if(array_key_exists($iusername, $this->players)){
			foreach($this->players[$iusername] as $key => $data){
				if($data[0] == $virusName){
					$this->players[$iusername][$key] = null;
					unset($this->players[$iusername][$key]);
					console("[DEBUG] $iusername has been recovered from virus $virusName", true, true, 4);
					return true;
				}
			}
		}
		return false;
	}
	
	public function checkInfected($username, PlagueVirus $virus){
		$name = $virus->getVirusName();
		foreach($this->players[$username] as $data){
			if($data[0] == $name){
				return true;
			}
		}
		return false;
	}
	
	public function __destruct(){}
	
	public function onRecoverSchedule($data){
		$player = $this->api->player->get($data, false);
		$tick = mt_rand(3600, 7200);
		$this->recoverySchedule[$data] = true;
		$this->api->schedule($tick, array($this, "onRecoverSchedule"), $data);
		if(!$player instanceof Player){
			return;
		}
		foreach($this->players[$data] as $p){
			$virus = $this->findVirusByName($p[0]);
			if(!$virus instanceof PlagueVirus){
				continue;
			}
			$addition = ceil((time() - $p[1]) / 3600);
			$level = abs($virus->getDangerLevel()) & 0x7FFFFFFF;
			if(mt_rand(0, max(0, $level + $addition)) == 0){
				$virus->onRecover($player);
			}
		}
	}
	
	private function createConfig(){
		$this->config = new Config(DATA_PATH."plugins/Plague/config.yml", CONFIG_YAML, array(
			"load-default-disease" => true
		));
	}
}

interface PlagueVirus{
	public function __construct(Plague $obj, $server); // $server could be instance of 'PocketMinecraftServer' (API 12) or 'MainServer' (API 13)
	/*
	@return : int[] - How could it infected?
	*/
	public function getInfectionInfo();
	/*
	@return : string - Virus name
	*/
	public function getVirusName();
	/*
	@return : int - How much is it dangerous? Is it can be easily recovered?
	*/
	public function getDangerLevel();
	/*
	@return : int - What is this virus's compatible version with Plague?
	*/
	public function getPlagueVersion();
	/*
	@param Player $player : Who are infected?
	@param int $route : What route did $player infected?
	*/
	public function onInfect(Player $player, $route);
	/*
	@param Player $player : Who is recovered?
	*/
	public function onRecover(Player $player);
}