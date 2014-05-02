<?php

/*
 * Welcome to OneboneDisease!
 * Example virus of Plague
 * 
 * If you infected by this disease, you'll get more fall damage
*/

class OneboneDisease implements PlagueVirus{
	private $server, $obj, $players, $record;
	
	public function __construct(Plague $obj, $server){
		$this->server = $server;
		$this->obj = $obj;
		@mkdir(DATA_PATH."plugins/Plague/Virus/OneboneDisease/");
		if(!is_file(DATA_PATH."plugins/Plague/Virus/OneboneDisease/players.dat")){
			file_put_contents(DATA_PATH."plugins/Plague/Virus/OneboneDisease/players.dat", serialize(array()));
		}
		$this->players = unserialize(file_get_contents(DATA_PATH."plugins/Plague/Virus/OneboneDisease/players.dat"));
		foreach($this->players as $player => $trash){
			$this->record[$player] = true;
		}
		$this->server->event("server.close", array($this, "onClose"));
		$this->server->addHandler("player.move", array($this, "onMove"));
	}
	
	public function getInfectionInfo(){
		return array(
			ROUTE_AIR => POSSIBILITY_LOW,
			ROUTE_WATER => POSSIBILITY_HIGH
		);
	}
	
	public function getVirusName(){
		return "Onebone Disease";
	}
	
	public function getDangerLevel(){
		return DANGER_HIGH;
	}
	
	public function onInfect(Player $player, $route){
		$this->players[$player->iusername] = true;
		console("[DEBUG] {$player->iusername} is infected by onebone disease", true, true, 4);
	}

	public function onRecover(Player $player){
		console("[DEBUG] {$player->iusername} is going to recover from onebone disease", true, true, 4);
		$this->players[$player->iusername] = null;
		unset($this->players[$player->iusername]);
		$this->obj->recoverMe($player->iusername, "Onebone Disease");
	}
	
	public function getPlagueVersion(){
		return 1;
	}
	
	public function onClose(){
		file_put_contents(DATA_PATH."plugins/Plague/Virus/OneboneDisease/players.dat", serialize($this->players));
	}
	
	public function onMove($data){
		if($data->player instanceof Player and isset($this->players[$data->player->iusername])){
			if(!$data->fallY === false){
				if($data->fallStart !== false and $this->record[$data->player->iusername] != $data->fallStart){
					$data->fallY += 3;
					$this->record[$data->player->iusername] = $data->fallStart;
				}
			}
		}
	}
}