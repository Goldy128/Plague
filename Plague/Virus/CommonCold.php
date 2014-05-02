<?php

/*
 * Hey, this is common cold! 
 * An example virus
 * 
 * If you get infected by this disease, you'll get damage in time
*/

class CommonCold implements PlagueVirus{
	private $obj, $players, $server;
	
	public function __construct(Plague $obj, PocketMinecraftServer $server){
		$this->obj = $obj;
		$this->server = $server;
		@mkdir(DATA_PATH."plugins/Plague/Virus/CommonCold/");
		if(!is_file(DATA_PATH."plugins/Plague/Virus/CommonCold/players.dat")){
			file_put_contents(DATA_PATH."plugins/Plague/Virus/CommonCold/players.dat", serialize(array()));
		}
		$this->players = unserialize(file_get_contents(DATA_PATH."plugins/Plague/Virus/CommonCold/players.dat"));
		foreach($this->players as $player => $tick){
			$this->server->schedule($tick, array($this, "onScheduleFunction"), $player, true);
		}
		$this->server->event("server.close", array($this, "onClose"));
	}
	
	public function getInfectionInfo(){
		return array(
			ROUTE_AIR => POSSIBILITY_HIGH,
			ROUTE_TOUCH => POSSIBILITY_VERY_HIGH
		);
	}
	
	public function getVirusName(){
		return "Common Cold";
	}
	
	public function getDangerLevel(){
		return DANGER_LOW;
	}
	
	public function onInfect(Player $player, $route){
		$tick = rand(1200, 3600);
		$this->players[$player->iusername] = $tick;
		$this->server->schedule($tick, array($this, "onScheduleFunction"), $player->iusername);
		console("[DEBUG] {$player->iusername} has been infected by common cold. Ticks : $tick", true, true, 4);
	}
	
	public function onRecover(Player $player){
		console("[DEBUG] {$player->iusername} is going to recover from common cold", true, true, 4);
		$this->players[$player->iusername] = null;
		unset($this->players[$player->iusername]);
		$this->obj->recoverMe($player->iusername, "Common Cold");
	}
	
	public function getPlagueVersion(){
		return 1;
	}
	
	public function onScheduleFunction($username){
		if($username == ""){
			return false;
		}
		$player = $this->server->api->player->get($username, false);
		$tick = rand(1200, 3600);
		$this->players[$username] = $tick;
		$this->server->schedule($tick, array($this, "onScheduleFunction"), $username);
		console("[DEBUG] $username has been scheduled by common cold. Ticks : $tick", true, true, 4);
		if(!$player instanceof Player){
			return;
		}
		console("[DEBUG] CommonCold : $username is getting hurt!", true, true, 4);
		$player->entity->harm(2, "commoncold");
	}
	
	public function onClose(){
		file_put_contents(DATA_PATH."plugins/Plague/Virus/CommonCold/players.dat", serialize($this->players));
	}
}

?>