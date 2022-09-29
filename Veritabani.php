<?php

class Veritabani{
	private $db=null;
	const VERITABANI="veritabani.sqlite";
	
	function __construct(){
		$ilkCalistirma = !file_exists(Veritabani::VERITABANI);
		$this->db = new SQLite3(Veritabani::VERITABANI);
		$this->db->enableExceptions(true);
		$this->db->busyTimeout(3000);
		if($ilkCalistirma){
			$this->tabloOlustur();	
		}
		
	}
	
	
	private function tabloOlustur(){
		$sorgular=array();
		$sorgular[]="CREATE TABLE IF NOT EXISTS ligler (
			id   INTEGER PRIMARY KEY,
			isim TEXT    NOT NULL,
			takimsayisi INTEGER DEFAULT 0);
		";
		$sorgular[]="CREATE TABLE IF NOT EXISTS takimlar (
			id   INTEGER PRIMARY KEY,
			ligid INTEGER DEFAULT 0,
			isim TEXT    NOT NULL,
			puan INTEGER DEFAULT 0,
			guc INTEGER DEFAULT 0,
			skor INTEGER DEFAULT 0
			);
		";
		$sorgular[]="CREATE TABLE IF NOT EXISTS karsilasmalar (
			id   INTEGER PRIMARY KEY,
			ligid INTEGER DEFAULT 0,
			takim1id INTEGER DEFAULT 0,
			takim2id INTEGER DEFAULT 0,
			takim1isim TEXT NOT NULL,
			takim2isim TEXT NOT NULL,			
			takim1skor INTEGER DEFAULT 0,
			takim2skor INTEGER DEFAULT 0,
			takim1puan INTEGER DEFAULT 0,
			takim2puan INTEGER DEFAULT 0,
			oynandi INTEGER DEFAULT 0,
			deplasman INTEGER DEFAULT 0,
			hafta INTEGER DEFAULT 0
			);
		";
		
		foreach($sorgular as $sorgu){
			$sonuc=$this->db->exec($sorgu);
		}

		
	}
	
	
	public function lastID(){
		return $this->db->lastInsertRowID();
	}
	
	public function ilkInteger($sorgu,$parametreler){
		$rows=$this->select($sorgu,$parametreler);
		if(!$rows || count($rows)<1) return 0;
		$row=$rows[0];
		return intval ($row[0]);
		
		
	}
	
	public function select($sorgu,$parametreler=null){	
		$array=array();
		try {
			$statement = $this->db->prepare($sorgu);
			for($i=0;isset($parametreler)&&$i<count($parametreler);$i++){
				$parametre=$parametreler[$i];
				$statement->bindValue($i+1,$parametre);
			}		
			$result = $statement->execute();
			if($result){
				while ($row = $result->fetchArray()) {
					array_push($array,$row);
				}
			}
					
		}catch(Exception  $e) {
			print ("exception " . $e->getMessage());
		}
		return $array;	
	}
	
	
	public function execute($sorgu,$parametreler=null){
		try {
			$statement = $this->db->prepare($sorgu);
			for($i=0;isset($parametreler)&&$i<count($parametreler);$i++){
				$parametre=$parametreler[$i];
				$statement->bindValue($i+1,$parametre);
			}		
			$result = $statement->execute();
			
			
			return $result;
					
		}catch(Exception  $e) {
			print ("exception " . $e->getMessage());
		}
		return false;
		
	}
}


?>