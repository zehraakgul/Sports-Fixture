<?php

include_once("Veritabani.php");
include_once("Hata.php");

class Fikstur{
	var $db;
	function __construct(){
		$this->db = new Veritabani();
		$islem = filter_input(INPUT_GET , "islem", FILTER_SANITIZE_STRING);
        if(!$islem){
           $islem = filter_input(INPUT_POST , "islem", FILTER_SANITIZE_STRING);
        }
		if($islem){ 
			$this->islemler($islem);
		}
	}
	
	
	private function islemler($islem){
		switch($islem){
			case "ligekle": return $this->ligEkle();
			case "ligsil": return $this->ligSil();
			case "oynat": return $this->oynat();
			case "haftaoynat": return $this->haftaoynat();
			case "temizle": return $this->temizle();
			case "takimismidegistir": return $this->takimIsmiDegistir();
			case "gucdegistir": return $this->gucDegistir();
		}
	}
	
	private function ligSil(){
		$ligID=filter_input(INPUT_GET , "ligid", FILTER_SANITIZE_NUMBER_INT);
		$this->db->execute("delete from ligler where id=?",[$ligID]);
		$this->db->execute("delete from takimlar where ligid=?",[$ligID]);
		$this->db->execute("delete from karsilasmalar where ligid=?",[$ligID]);
		header("location:index.php");
	}
	
	private function temizle(){
		$ligID=filter_input(INPUT_POST , "ligid", FILTER_SANITIZE_NUMBER_INT);
		$takimSayisi = $this->db->ilkInteger("select takimsayisi from ligler where id=?",[$ligID]);
		if($takimSayisi<1) new Hata("Geçersiz lig");
		
		$this->db->execute("update takimlar set guc=?, puan=0, skor=0 where ligid=?",[$takimSayisi,$ligID]);
		$this->db->execute("update karsilasmalar set 
							takim1skor=0,
							takim2skor=0,
							takim1puan=0,
							takim2puan=0,
							oynandi=0			
							where ligid=?",
						  [$ligID]);
		
	}
	private function haftaoynat(){$ligID=filter_input(INPUT_POST , "ligid", FILTER_SANITIZE_NUMBER_INT);		
		$takimSayisi = $this->db->ilkInteger("select takimsayisi from ligler where id=?",[$ligID]);
		if($takimSayisi<1) new Hata("Geçersiz lig");
		for($i=0;$i<$takimSayisi/2;$i++){
			$this->oynat();
		}
		header("location:index.php?ligid={$ligID}");
		
		
		
	}
	
	private function oynat(){
		$ligID=filter_input(INPUT_POST , "ligid", FILTER_SANITIZE_NUMBER_INT);		
		$takimSayisi = $this->db->ilkInteger("select takimsayisi from ligler where id=?",[$ligID]);
		if($takimSayisi<1) new Hata("Geçersiz lig");
		
		$sorgu = "select id,takim1id,takim2id,
			(select guc from takimlar where id=takim1id) as takim1guc,
			(select guc from takimlar where id=takim2id) as takim2guc
			from karsilasmalar where oynandi=0 and ligid=?
			order by hafta asc
			limit 1
			";
			
		$rows = $this->db->select($sorgu,[$ligID]);
		if(!$rows || count($rows)<1) {
			header("location:index.php?ligid={$ligID}");
			exit(0);
		}
		$karsilasma = $rows[0];
		$karsilasmaID=$karsilasma["id"];
		$takim1Guc = $karsilasma["takim1guc"];
		$takim1Min=$takim1Guc-$takimSayisi;
		if($takim1Min<0) $takim1Min = 0;
		//$takim1Skor=mt_rand($takim1Min, $takim1Guc+$takim1Min);
		$takim2Guc = $karsilasma["takim2guc"];
		$takim2Min=($takim2Guc-$takimSayisi);
		if($takim2Min<0) $takim2Min = 0;
		//$takim2Skor=mt_rand($takim2Min, $takim2Guc+$takim2Min);
		$takim1Puan =0;
		$takim2Puan=0;
		$maxGuc = $takimSayisi*2;
		$maxGol = 6;
		$takim1Skor=$takim2Skor=0;
		for($i=0;$i<$maxGol;$i++){
			
			$takim1Skor+=  round(mt_rand(0,($takim1Guc/$maxGuc)*200)/100);
			$takim2Skor+=  round(mt_rand(0,($takim2Guc/$maxGuc)*200)/100);			
		}
		
		
		if($takim1Skor>$takim2Skor){
			$takim1Puan=3;
			$takim1Guc++;
			$takim2Guc--;
			if($takim2Guc<0){
				$takim2Guc=0;
			}
		}else if($takim2Skor>$takim1Skor){
			$takim2Puan=3;
			$takim2Guc++;
			$takim1Guc--;
			if($takim1Guc<0){
				$takim1Guc=0;
			}
		}else{
			$takim1Puan=$takim2Puan=1;
		}

		$sorgu = "update karsilasmalar set 
						oynandi=1, 
						takim1skor=?,
						takim2skor=?,
						takim1puan=?,
						takim2puan=?
				where id=?";
		$this->db->execute($sorgu,
				[$takim1Skor,$takim2Skor,$takim1Puan,$takim2Puan,$karsilasmaID]
		);
		
		$this->db->execute("update takimlar set puan=puan+?,skor=skor+?, guc=? where id=?",[
						$takim1Puan,$takim1Skor,$takim1Guc,$karsilasma["takim1id"]]);
		$this->db->execute("update takimlar set puan=puan+?,skor=skor+?, guc=? where id=?",[
						$takim2Puan,$takim2Skor,$takim2Guc,$karsilasma["takim2id"]]);
			
		
		header("location:index.php?ligid={$ligID}");
		
		
	}
	
	private function ligEkle(){
		$ligismi = $_POST['isim'];
		$takimsayisi = filter_input(INPUT_POST , "takimsayisi", FILTER_SANITIZE_NUMBER_INT);
		
		if(strlen($ligismi)<1 || $takimsayisi<6 || $takimsayisi>20 || $takimsayisi%2!=0){
			new Hata("Geçersiz giriş. Lig ismi girilmeli. Takım sayısı 6 ile 20 arası çift sayı olmalı");
		}
		$sorgu = "insert into ligler (isim,takimsayisi) values (?,?)";
		$this->db->execute($sorgu,[$ligismi,$takimsayisi]);	
		$ligID=$this->db->lastID();
		
		$takimlar=array();
		for($i=0;$i<$takimsayisi;$i++){
			$takimismi="Takım".($i+1);
			$guc = $takimsayisi;
			$sorgu = "insert into takimlar(ligid,isim,guc) values(?,?,?)";
			$this->db->execute($sorgu,[$ligID,$takimismi,$guc]);		
			$takimID=$this->db->lastID();
			$takimlar[]=new Takim($takimismi,$takimID);
		}

			$karsilasmalar = $this->karsilastir($takimlar);		
		foreach($karsilasmalar as $karsilasma){
			$sorgu = "insert into karsilasmalar(ligid,takim1id,takim2id,takim1isim,takim2isim,hafta)
				values(?,?,?,?,?,?)";
			
			$parametreler = [	$ligID,
								$karsilasma->takim1->id,
								$karsilasma->takim2->id,
								$karsilasma->takim1->isim,
								$karsilasma->takim2->isim,
								$karsilasma->hafta];
			$this->db->execute($sorgu,$parametreler);
		}
		$karsilasmalar_dep = $this->karsilastir_dep($takimlar);		
		foreach($karsilasmalar_dep as $karsilasma_dep){
			$sorgu_dep = "insert into karsilasmalar(ligid,takim1id,takim2id,takim1isim,takim2isim,hafta,deplasman)
				values(?,?,?,?,?,?,?)";
			
			$parametreler_dep = [	$ligID,
								$karsilasma_dep->takim1->id,
								$karsilasma_dep->takim2->id,
								$karsilasma_dep->takim1->isim,
								$karsilasma_dep->takim2->isim,
								$karsilasma_dep->hafta,
								$karsilasma_dep->deplasman];
			$this->db->execute($sorgu_dep,$parametreler_dep);
		}
		
		header("location:index.php?ligid={$ligID}");
		
	}
	
	
	function karsilastir($teams){
		$rakipler = array_splice($teams,(count($teams)/2));  //takımların ilk yarısı
		$takimlar = $teams;
		$deplasman = 0;
		$hafta_=0;
		for ($i=0; $i < count($takimlar)+count($rakipler)-1; $i++){
			for ($j=0; $j<count($takimlar); $j++){
				$takim1=$takimlar[$j];
				$takim2=$rakipler[$j];
				if(($i%2==0)) {		//	hangi takımın ev sahibi olacağını düzgünce belirle
					$takim1=$rakipler[$j];
					$takim2=$takimlar[$j];
				}
			
				$karsilasmalar[]= new Karsilasma($i,$takim1,$takim2,$deplasman);				
			}
			$hafta_=$hafta_+2;
			if(count($takimlar)+count($rakipler)-1 > 2){
				array_unshift($rakipler, current(array_splice($takimlar,1,1)) );
				array_push($takimlar,array_pop($rakipler));
				
			}
		}
		shuffle($karsilasmalar);	// karşılaşma sırasını karıştır
		usort($karsilasmalar,function($a, $b) {return $a->hafta-$b->hafta;});	// karşılaşmaları haftaya göre sırala
		return $karsilasmalar;
	}

	function karsilastir_dep($teams){
		$rakipler = array_splice($teams,(count($teams)/2));  //takımların ilk yarısı
		$takimlar = $teams;
		$deplasman = 1;
		$hafta_dep = 1;
		$sayilar = array();
		$sayi = ((count($takimlar)+count($rakipler))-1);
		
		for ($i=0; $i < count($takimlar)+count($rakipler)-1; $i++){

			for ($j=0; $j<count($takimlar); $j++){
				$takim1=$rakipler[$j];
				$takim2=$takimlar[$j];
				if(($i%2==0)) {	
					$takim1=$takimlar[$j];
					$takim2=$rakipler[$j];
				}
				
				$karsilasmalar_dep[]= new Karsilasma($sayi,$takim1,$takim2,$deplasman);				
			}
			$sayi+=1;
			$hafta_dep = $hafta_dep+2;
			if(count($takimlar)+count($rakipler)-1 > 2){
				array_unshift($rakipler, current(array_splice($takimlar,1,1)) );
				array_push($takimlar,array_pop($rakipler));
				
			}
		}
		shuffle($karsilasmalar_dep);	// karşılaşma sırasını karıştır
		usort($karsilasmalar_dep,function($a, $b) {return $a->hafta-$b->hafta;});	// karşılaşmaları haftaya göre sırala
		return $karsilasmalar_dep;
	}
		
		
	function ligListesi(){
		$sorgu="select id,isim,takimsayisi from ligler";
		$rows = $this->db->select($sorgu);
		if(!$rows || count($rows)<1) return "Lig tanımlanmamış";
		$table="<div>";
		foreach($rows as $row){
			$ligID = $row["id"];
			$table.="
				<div class='liglistesi'>
					<a href='?ligid={$ligID}'>  {$row["isim"]}</a> 
					<br/><br/>
					<a href='?ligid={$ligID}&islem=ligsil' name = 'Sil'  style='background-color: #e6ded1;' >Sil</a>
				</div>
				";
		}
		$table.="</div><div style='clear:both'> </div><br/>";
		
		return $table;		
	}
	
	function karsilasmaListesi(){
		
		$ligID = filter_input(INPUT_GET , "ligid", FILTER_SANITIZE_NUMBER_INT);
		if($ligID<1) return "";
		$sorgu="select id,takim1id,takim2id,takim1skor,takim2skor,
						(select isim from takimlar where id=takim1id) as takim1isim,
						(select isim from takimlar where id=takim2id) as takim2isim,
						takim1puan,takim2puan,oynandi,hafta,deplasman
						from karsilasmalar 
						where ligid=? 
						order by hafta,id";
		$rows = $this->db->select($sorgu,[$ligID]);
		if(!$rows || count($rows)<1) return "";
		
		$liste="<center>
				<br>
				<table>
				<form action='#' method='post'>
				<input type='submit' name='Oynat' value='Sıradaki maçı oynat' style='background-color: #04AA6D;border: none;
				color: white;
				padding: 15px;
				text-align: center;
				border-radius: 24px;'/>
				<input type='hidden' name='islem' value='oynat' />
				<input type='hidden' name='ligid' value='{$ligID}' />
        </form>
		<form action='#' method='post'>
			<input type='submit' name='Oynat' value='Sıradaki haftayı oynat' style='background-color: #006699;border: none;
			color: white;
			padding: 15px;
			border-radius: 24px;
			margin: 2px;'/>
			<input type='hidden' name='islem' value='haftaoynat' />
			<input type='hidden' name='ligid' value='{$ligID}' />			
		</form>		
		</table>
		<br>
			
		";
		
		
		
		$liste.= "<hr data-content='1. Devre'>
		<div class='haftadiv'> 1. Hafta (1. Devre)<br/><br/><table>";
		$oncekiHafta=1;
		foreach($rows as $row){
			$id = $row["id"];
			$hafta=$row["hafta"]+1;
			$deplasman = $row["deplasman"];
			
			if($hafta!=$oncekiHafta and $deplasman ==1){
			$liste.="</table></div><div class='haftadiv'>{$hafta}. Hafta (2. Devre)<br/><br/><table id='haftatablo'>";
			}elseif($hafta!=$oncekiHafta and $deplasman ==0){
				$liste.="</table></div><div class='haftadiv'>{$hafta}. Hafta (1. Devre)<br/><br/><table id='haftatablo'>";
			}
			$oynandi = $row["oynandi"];
			$takimSkor=$row["takim1skor"];
			$rakipSkor=$row["takim2skor"];
			if(!$oynandi){
				$takimSkor="-";
				$rakipSkor="-";
			}
			
				$liste.="<tr id='haftalistetablo'>
				<td width=80>{$row["takim2isim"]}  </td>
				<td width=40>{$rakipSkor}  </td>
				<td width=40>{$takimSkor}  </td>
				<td width=80>{$row["takim1isim"]}  </td>
				</tr>";
			$oncekiHafta = $hafta;
		}
		$liste.="</table></div>";
		
		$liste.="
				<div style='clear:both'>
					<form action='#' method='post'>
					<input type='submit' name='Temizle' value='Puanları sıfırla'style='background-color: #CC0000;border: none;
					color: white;
					padding: 15px;
					text-align: center;
					border-radius: 24px;'/>
					<input type='hidden' name='islem' value='temizle' />
					<input type='hidden' name='ligid' value='{$ligID}' />			
					</form>
				</div>
				</center>
		";
		
		
		
		return $liste;		
	}

		
	function puanTablosu(){		
		$ligID = filter_input(INPUT_GET , "ligid", FILTER_SANITIZE_NUMBER_INT);
		if($ligID<1) return "";
		$sorgu="select id,isim,guc, puan,skor from takimlar t where ligid=? order by puan desc,skor desc";
		$rows = $this->db->select($sorgu,[$ligID]);
		if(!$rows || count($rows)<1) return "";
		
		$liste="<center><table id = 'takimtablo'><tr id = 'takimlistetablobaslik'><td>İsim</td><td>Puan</td><td>Atılan Gol</td><td>Güç</td></tr>";
		foreach($rows as $row){
			$takimID=$row["id"];
			$liste.="<tr>
				<td id= 'takimlistetablo' width=180>
					<form action='#' method='post'>
					<input type='text' name='takimismi' id='takimismi' value=\"{$row["isim"]}\" style='width:100px;float:left;'/>  
					<input type='image' id = 'guncelle' src='guncelle.png' alt='Submit' 
						style='float:left;margin-left:10px;' width=24 height=24 />
					<input type='hidden' name='ligid' value={$ligID} />
					<input type='hidden' name='takimid' value={$takimID} />					
					<input type='hidden' name='islem' value='takimismidegistir' />					
					
					</form>				
				
				<td id='takimlistetablo' width=50>{$row["puan"]}  </td>
				<td id='takimlistetablo' width=70>{$row["skor"]}  </td>
				<td id= 'takimlistetablo' width=100>
					<form action='#' method='post'>
					<input type='number' name='guc' id='guc' value=\"{$row["guc"]}\" style='width:50px;float:left;' min='0'/>  
					<input type='image' id = 'save' src='save.png' alt='Submit' 
						style='float:left;margin-left:10px;' width=24 height=24 />
					<input type='hidden' name='ligid' value={$ligID} />
					<input type='hidden' name='takimid' value={$takimID} />					
					<input type='hidden' name='islem' value='gucdegistir' />
					<!--<td id='takimlistetablo'width=40>{$row["guc"]}  </td>-->
					</form>	
					
				
				</tr>";
		}
		$liste.="</table></center>";
		return $liste;		
	}
	
	
	function takimIsmiDegistir(){
		$ligID = filter_input(INPUT_POST , "ligid", FILTER_SANITIZE_NUMBER_INT);
		$takimID = filter_input(INPUT_POST , "takimid", FILTER_SANITIZE_NUMBER_INT);
		$takimIsmi = filter_input(INPUT_POST , "takimismi", FILTER_SANITIZE_STRING);
		$sorgu = "update takimlar set isim = ? where id= ?";
		$this->db->execute($sorgu,[$takimIsmi,$takimID]);
		header("location:index.php?ligid={$ligID}");
	}
	function gucDegistir(){
		$ligID = filter_input(INPUT_POST , "ligid", FILTER_SANITIZE_NUMBER_INT);
		$takimID = filter_input(INPUT_POST , "takimid", FILTER_SANITIZE_NUMBER_INT);
		$takimguc = filter_input(INPUT_POST , "guc", FILTER_SANITIZE_NUMBER_INT);
		$sorgu = "update takimlar set guc = ? where id= ?";
		$this->db->execute($sorgu,[$takimguc,$takimID]);
		header("location:index.php?ligid={$ligID}");
	}
	
	
	
	
}









class Takim{
	var $isim;
	var $id;	
	
	function __construct($isim,$id){
		$this->isim=$isim;
		$this->id=$id;
	}
	
}

class Karsilasma{
	var $takim1;
	var $takim2;
	var $hafta;
	var $deplasman;
	
	function __construct($hafta,$t1,$t2,$deplasman){
		$this->takim1=$t1;
		$this->takim2=$t2;
		$this->hafta=$hafta;
		$this->deplasman=$deplasman;
	}
}


$fikstur=new Fikstur();








?>


<html>
	<head>
	<style>
		body{
			background-color: #e6ded1;
		}
		fieldset {
		border: 1px solid #eee;
	 	padding: 12px 35px;
	 	text-align: center;
		font-family: 'Arial';
		border-top: 2px solid #04AA6D;
 		border-bottom: 2px solid #04AA6D;
  		box-shadow: 0px 0px 20px rgba(0,0,0,0.10);
		}
		.liglistesi{
			padding-right:10px;
			float:left; 
			font-family: 'Arial';
  			border-collapse: collapse;
 			border: 1px solid #eee;
			border-top: 2px solid #04AA6D;
 			border-bottom: 2px solid #04AA6D;
  			box-shadow: 0px 0px 20px rgba(0,0,0,0.10);
			border-radius:10px;
			margin:20px; 
			padding:10px;
		}
		.haftadiv{
			float:left;
			padding:20px;
			border-radius: 15px;
			margin-left:30px;
			margin-right:20px;
			margin-bottom:30px;
			font-family: 'Arial';
  			border-collapse: collapse;
 			border: 1px solid #eee;
			border-top: 2px solid #04AA6D;
 			border-bottom: 2px solid #04AA6D;
  			box-shadow: 0px 0px 20px rgba(0,0,0,0.10);
		} 
		input[type=submit]:hover{
			filter: brightness(50%);
		}
		#LigEkle{
			background-color: #04AA6D;
			border: none;
			color: white;
			padding: 10px;
			text-align: center;
			border-radius: 16px;
			box-shadow: 0px 0px 20px rgba(0,0,0,0.10);

		}
		#guncelle,#save{
			transition: transform .2s;
		}
		#guncelle:hover,#save:hover{
			transform: scale(1.5);
		}

		#takimtablo{
			height: 50px;
			font-family: 'Arial';
  			border-collapse: collapse;
 			border: 1px solid #eee;
 			border-bottom: 2px solid #04AA6D;
  			box-shadow: 0px 0px 20px rgba(0,0,0,0.10);

			  
  		}

		#takimlistetablo {
    	
   		border: 1px solid #eee;
		padding: 5px;
		text-align: center;
		
		
  		}
		#takimlistetablo:hover{
			background: #f4f4f4;
			font-weight: bold;
		}
		#takimlistetablobaslik{
			background-color: #04AA6D;
    		color: #fff;
   		 	font-size: 16px;
			padding: auto;
			text-align: center;
			}
		#haftalistetablo {
    	
		border: 1px solid #eee;
	 	text-align: center;
		font-family: 'Arial';
		border-top: 2px solid #04AA6D;
 		border-bottom: 2px solid #04AA6D;
  		box-shadow: 0px 0px 20px rgba(0,0,0,0.10);
	 
	 
		}
	 	#haftalistetablo:hover{
		 background: #f4f4f4;
		 font-weight: bold;
		 }

#isim, #takimsayisi, #takimismi, #guc{
  font-family: inherit;
  border: 0;
  border-bottom: 1px solid black;
  outline: 0;
  padding: 1px 0;
  background: transparent;

}

#labelisim, #labeltakimsayisi {
  top: 0;
  display: block;
  color: black;
}

  			
	</style>
	</head>



    <body>
        <form action="#" method="post">
		<fieldset>
			
			<legend>Lig Ekle</legend>
			<label for="isim" id="labelisim">İsim:</label>
            <input type="text" id="isim" name="isim" required/><br>
			<label for="takimsayisi" id="labeltakimsayisi">Takım Sayısı:</label>
            <input type="text" id="takimsayisi" name="takimsayisi" required/><br><br>
            <input type="submit" name="SubmitButton" id='LigEkle' value="Lig Ekle"/>
			<input type="hidden" name="islem" value="ligekle" />

		
		</fieldset>
        </form>
		
		<?php echo $fikstur->ligListesi() ?>
		<?php echo $fikstur->puanTablosu() ?>
		<?php echo $fikstur->karsilasmaListesi() ?>
		
		
    </body>
</html>