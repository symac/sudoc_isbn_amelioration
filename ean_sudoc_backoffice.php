<?php
  /*
    Cette fonction est liée au script ean_sudoc et c'est elle qui va tourner en backoffice.
    On lui passe en paramètre le nom fichier qu'on vient d'uploader et en retour elle envoie
    une liste d'isbn
    
    Historique
      - 20111222 : Utilisation du WS isbn2ppn
      - 20111118 : Première version en ligne
  */
	$BASE_ISBN2PPN = "http://www.sudoc.fr/services/isbn2ppn/";
	$SEPARATOR = "";
	$INTERRO_SIMUL = 100;
	
  $sortie = Array();
  $sortie["statut"] = 1;
  $sortie["res"] = "";
  if (isset($_GET['filename']))
  {
    $file = $_GET['filename'];
  }
  else
  {
		BUG("Problème dans l'upload du fichier");
  }
    
  $fp = fopen("tmp/".$file,"r"); //lecture
  $stderr = fopen('php://stderr', 'w');
	
	// On va préparer le fichier de sortie
	$fo = fopen("out/".$file, 'w');
  

  // $H est un tableau qui nous sert à éviter de traiter les EAN en double
  $H = Array();
  
  // $req est la requête que l'on va passer au sudoc pour éviter d'appeler deux fois
	$req = "";
  
	/***********************************************
		1. On va charger l'intégralité du fichier ISBN
	***********************************************/
	$tab_global = Array();
	// Compteur temporaire
  $nb = 0;	
  while(!feof($fp)) {
		$ligne = fgets($fp,255);
    $ligne = chop($ligne);
		
		if ($nb == 0)
		{
			if (preg_match("/\t/", $ligne))
			{
				$SEPARATOR = "\t";
			}
			elseif (preg_match("/;/", $ligne))
			{
				$SEPARATOR = ";";
			}
		}
		
		if ($SEPARATOR != "")
		{
			$tab_ligne = split($SEPARATOR, $ligne);
			$isbn = $tab_ligne[0];
			$fin_ligne = join($SEPARATOR, array_slice($tab_ligne, 1));
		}
		else
		{
			$isbn = $ligne;
			$fin_ligne = "";
		}
		
		$tab_ligne = Array();
		$tab_ligne["isbn"] = $isbn;
		// Par défaut on met 0, ça évite d'avoir à faire la correspondance entre ce que
		// le sudoc nous renvoie et ce qu'il ne nous renvoie pas
		$tab_ligne["tq"] = 0;
		$tab_ligne["tq2"] = 0;
		$tab_ligne["fin_ligne"] = $fin_ligne;

		if ($ligne != "")
		{
			$tab_global[] = $tab_ligne;
		}
	}

	/***********************************************************
		2. On va chercher le nombre de résultats pour chaque ISBN
		tel quel, sans modifier ce qui nous est passé (seulement
		en faisant un peu de nettoyage)
	***********************************************************/
	$i = 0;
	$cpt = 0;
	$req = "";
	$tab_equiv = Array();
	
	while ($i < sizeof($tab_global))
	{
		$isbn = $tab_global[$i]["isbn"];
		if ($req != "")
		{
			$req .= ",";
		}
		$req .= $isbn;
		
		if (isset($tab_equiv["$isbn"]))
		{
			$tab_equiv["$isbn"] .= "#".$i;
		}
		else
		{
			$tab_equiv["$isbn"] = $i;
		}
		
		$cpt++;
		
		if ($cpt == $INTERRO_SIMUL)
		{
			requete_WS($req, "tq", $tab_equiv);
			$req = "";
			$cpt = 0;
			$tab_equiv = Array();
		}
		$i++;
	}
	// On lance une dernière fois la requête
	if ($req != "") { requete_WS($req, "tq", $tab_equiv); }

	/************************************************************
		3. On va aller regarder si on a plus de réussite avec le
		deuxième ISBN (10->13 ou 13->10) pour ceux qui ont toujours
		0 résultats.
	************************************************************/
	$i = 0;
	$cpt = 0;
	$req = "";
	$tab_equiv = Array();
	while ($i < sizeof($tab_global))
	{
		$tq1 = $tab_global[$i]["tq"];
		if ($tq1 == 0)
		{
			$isbn = $tab_global[$i]["isbn"];
			// On va transformer l'isbn dans sa deuxième forme.
			// - ISBN-10 => ISBN-13
			// - ISBN-13 => ISBN-10
			$isbn2 = isbn_autre_version($isbn);
			if ($isbn2 == "")
			{
//				print "Erreur pour transfo de #$isbn# [$i]<br/>";
			}
			else
			{
				$tab_global[$i]["isbn2"] = $isbn2;
				if ($req != "")
				{
					$req .= ",";
				}
				$req .= $tab_global[$i]["isbn2"];
				
				if (isset($tab_equiv[$tab_global[$i]["isbn2"]]))
				{
					$tab_equiv[$tab_global[$i]["isbn2"]] .= "#".$i;
				}
				else
				{
					$tab_equiv[$tab_global[$i]["isbn2"]] = $i;
				}
				
				
				$cpt++;
				if ($cpt == $INTERRO_SIMUL )
				{
					requete_WS($req, "tq2", $tab_equiv);
					$req = "";
					$cpt = 0;
					$tab_equiv = Array();
				}
			}
		}
		$i++;
	}
	requete_WS($req, "tq2", $tab_equiv);
	
	/********************************************
		4. On va faire des statistiques à partir de
		ce qu'on a rempli dans le tableau
	********************************************/
	$nb_isbn_src = sizeof($tab_global);
	$tab_stats = Array();
	$tab_stats["ok_base"] = 0;
	$tab_stats["trop_base"] = 0;
	$tab_stats["ok_v2"] = 0;
	$tab_stats["trop_v2"] = 0;
	$tab_stats["ko"] = 0;
	
	$sortie["repartition"] = Array();
	foreach ($tab_global as $res_un_isbn)
	{
		if ($res_un_isbn['tq'] == 1)
		{
			$sortie["repartition"]["1"]++;
			$tab_stats["ok_base"]++;
		}
		elseif ($res_un_isbn['tq'] > 1)
		{
			$tab_stats["trop_base"]++;
			$sortie["repartition"][$res_un_isbn['tq']]++;
		}
		else
		{
			if ($res_un_isbn['tq2'] == 1)
			{
				$tab_stats["ok_v2"]++;
				$sortie["repartition"][$res_un_isbn['tq2']]++;
			}
			elseif ($res_un_isbn['tq2'] > 1)
			{
				$tab_stats["trop_v2"]++;
				$sortie["repartition"][$res_un_isbn['tq2']]++;
			}
			else
			{
				$tab_stats["ko"]++;
				// Si on n'a pas de résultats avec le deuxième ISBN on en avait
				// peut-être avec le premier
				$sortie["repartition"][$res_un_isbn['tq2']]++;
			}
		}
	}
	
	ksort($sortie["repartition"]);
	
	/***************************************
		5. On va préparer le fichier de sortie
	***************************************/
	$sortie["stats"]["nb_total"]		= $nb_isbn_src;
	$sortie["stats"]["pct_init"]		= (($tab_stats["ok_base"] / $nb_isbn_src)*100);
	$sortie["stats"]["nb_init"]	= $tab_stats["ok_base"];
	$sortie["stats"]["pct_v2"] 			= ((( $tab_stats["ok_base"] + $tab_stats["ok_v2"] ) / $nb_isbn_src)*100);
	$sortie["stats"]["nb_v2"]		= $tab_stats["ok_base"] + $tab_stats["ok_v2"];
	$sortie["stats"]["pct_augment"] = sprintf("%0.2f", (($tab_stats["ok_v2"] / $tab_stats["ok_base"]) * 100));
	

	
	$sortie["url_fic"]  = dirname('http://'.$_SERVER['SERVER_NAME'].$_SERVER["PHP_SELF"])."/out/".$file;
	
	// On va préparer le fameux fichier de sortie
	foreach ($tab_global as $une_ligne)
	{
		$ligne_sortie = "";
		$isbn_ok = "";
		if ($une_ligne["tq"] >= 1)
		{
			$isbn_ok = $une_ligne["isbn"];
		}
		elseif ($une_ligne["tq2"] >= 1)
		{
			$isbn_ok = $une_ligne["isbn2"];
		}
		else
		{
			// Si l'isbn retravaillé ne donne rien, on conserver l'isbn
			// original histoire de perturber au minimum le fichier de base
			$isbn_ok = $une_ligne["isbn"];
		}
		
		if ($SEPARATOR != "")
		{
			$ligne_sortie .= $isbn_ok.$SEPARATOR.$une_ligne["fin_ligne"];
		}
		else
		{
			$ligne_sortie .= $isbn_ok;
		}
		fwrite($fo, $ligne_sortie."\n");
	}
	
	// print "<a href='".$sortie["url_fic"]."'>lien</a><br/>";
	print json_encode($sortie);
	exit;	
	print "<ul>\n";
	print "<li>Nombre d'isbn analysés : ".$nb_isbn_src."</li>\n";
	print "<li>Réponses avec l'isbn source : <ul>";
	print "<li>1 réponse : ".$tab_stats["ok_base"]."</li>";
	print "<li>Plusieurs réponses : ".$tab_stats["trop_base"]."</li>";
	print "</ul></li>";
	print "<li>Réponses avec l'isbn retravaillé : <ul>";
	print "<li>1 réponse : ".$tab_stats["ok_v2"]."</li>";
	print "<li>Plusieurs réponses : ".$tab_stats["trop_v2"]."</li>";
	print "</ul></li>";
	print "<li>Aucune réponse : ".$tab_stats["ko"]."</li>";
	print "<li>Taux de recouvrement brut : ".(($tab_stats["ok_base"] / $nb_isbn_src)*100)."</li>";
	print "<li>Taux de recouvrement retravaillé : ".((( $tab_stats["ok_base"] + $tab_stats["ok_v2"] ) / $nb_isbn_src)*100)."</li>";
	print "</ul>\n";
	
	exit;
	
	function requete_WS($req, $code_zone, $tab_equiv)
	{
		global $tab_global;
		global $BASE_ISBN2PPN;

		// On interroge le WEB service
		// TODO : traiter les erreurs s'il y en a
		$string_xml = @file_get_contents($BASE_ISBN2PPN.$req);
		if ($string_xml === false)
		{
			// Ici, aucun des isbn que l'on a passé ne répond correctement
			// On reçoit une erreur 404. On cache le warning à l'aide du @
			// en préfixe de file_get_contents et l'on traite avec le ===
		}
		else
		{
			$res = simplexml_load_string($string_xml );
	
			// On va parcourir la liste pour avoir le nombre de résultats pour
			// chacun des isbn que l'on a fait passer.
			foreach ($res->query as $un_res)
			{
				$local_isbn = (string) $un_res->isbn;
				$nb_res = sizeof($un_res->result->ppn);
				// On va mettre à jour le tableau global à partir de ces infos
				
				$tab_id = split("#", $tab_equiv[$local_isbn]);
				foreach ($tab_id as $un_id)
				{
					if ($un_id != "")
					{
						$tab_global[$un_id][$code_zone] = $nb_res;
					}
				}
			}
		}
	}

	// Cette fonction va transformer un isbn10 en isbn13 et inversement  
	function isbn_autre_version($isbn)
	{
		$isbn = str_replace("-", "", $isbn);
		$isbn = str_replace(" ", "", $isbn);
		
		if (strlen($isbn) == 10)
		{
			return isbn10_to_13($isbn);
		}
		elseif (strlen($isbn) == 13)
		{
			return isbn13_to_10($isbn);
		}
		else
		{
			return "";
		}
	}
	
  
  function isbn13_to_10($x)
  {
		$t = 0;
    $x = str_replace("-","",$x);
    $x = str_replace(" ","",$x);
    if(strlen($x) == 13)
    {
      $x = substr($x,-10,9);
      $k = str_split($x);
      $m = 10;
      foreach($k as $K)
      {
        $K = $K*$m;
        $t += $K;
        $m--;
      }
      $k = 11 - ($t % 11);
      if ($k == 10) { $k = "X"; }
      if ($k == 11) { $k = "0"; }
      $x = $x.$k;
      }
    return $x;
  }
	
	function isbn10_to_13($isbnold)
	{
		if (!preg_match('#^[0-9]{10}$#', $isbnold))
		{
			return '';
		}
		
		// Don't forget to set checksumtotal to initial value
		$check_sum_total = 0;
		// prefix with 978 and drop old checksum (last digit)
		$isbn = '978'.substr($isbnold,0,9);
		
		for ($i = 0; $i <= 11; $i++){ // For each digit of new isbn
		// multiply each digit by 1 or 3 and add to $checksumtotal
		$check_sum_total = $check_sum_total + ($isbn[$i] * (($i % 2 == 0) ? 1 : 3) );
		}
		
		$new_check_sum = 9 - (($check_sum_total + 9) %10); // Modulus 10 business
		
		return ($isbn.$new_check_sum); //add checksum on to end and return
	}
	
	function BUG($message)
	{
		global $sortie;
		$sortie["statut"] = 0;
	  $sortie["res"] = $message;
		print json_encode($sortie);
		exit;
	}
?>