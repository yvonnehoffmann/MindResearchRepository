<?php
class RpositoryDAO extends DAO{
    public function getDateFilename($article_id){
        $result =& $this->retrieve('SELECT date, fileName FROM rpository WHERE articleId = ? AND current = 1', array($article_id));
        $return_value = array();
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            
            $return_value['date']       = $row['date'];
            $return_value['filename']   = $row['filename'];
        }
        $result->Close();
        return $return_value;
    }
    
    public function delCurrentEntry($article_id){
// Insert into OldPID( Select articleId, pid1, pid2 from rpository Where articleID= ?  AND current = !')
      $insertCheck = $this->update('Insert into rpository_unused_pid( Select * from rpository Where articleId = ? AND current = 1)', array($article_id));
      $deleteCheck = $this->update('DELETE FROM rpository WHERE articleId = ? AND current = 1', array($article_id));
      if($insertCheck AND $deleteCheck){
         return TRUE;
      }else{
         return FALSE;
      }
      //  return $this->update('Insert into rpository_unused_pid( Select * from rpository Where articleId = ? AND current = 1); DELETE FROM rpository WHERE articleId = ? AND current = 1', array($article_id,$article_id));
    }
    
    public function getJournalId($article_id){
        $result =& $this->retrieve('SELECT journals.journal_id FROM journals INNER JOIN articles ON journals.journal_id = articles.article_id WHERE article_id = ?', array($article_id));
        $return_value = NULL;
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            //error_log(print_r($row, true));
            $return_value = $row['journal_id'];
        }
        $result->Close();
        return $return_value;
    }
    
    public function insertNewEntry($article_id, $filename, $pidv1 = NULL, $pidv2 = NULL, $major = 1, $minor = 0){
        error_log('OJS - Rpository: Insert new Entry wurde aufgerufen.');
	return $this->update("INSERT INTO rpository (articleId, fileName, current, major, minor, date, pidv1, pidv2) VALUES (?, ?, 1, ?, ?, CURDATE(), ?, ?)", array($article_id, $filename, $major, $minor, $pidv1, $pidv2));
    }
    public function test ($articleId){
        return $this->update("INSERT INTO rpository_unused_pid (Select * from rpository where articleId=?)", array($articleId));
	}
	
    
    public function getJournalPath($article_id){
        $result =& $this->retrieve('SELECT DISTINCT path FROM journals INNER JOIN articles ON journals.journal_id = articles.article_id WHERE article_id = ?', array($article_id));
        $return_value = NULL;
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            //error_log(print_r($row, true));
            $return_value = $row['path'];
        }
        $result->Close();
        return $return_value;
    }
    
    public function getArtStatement($article_id){
        $result =& $this->retrieve("SELECT published_articles.issue_id, published_articles.date_published, "
                . "S1.setting_value AS sv1, S2.setting_value AS sv2 FROM published_articles "
                . "JOIN article_settings S1 ON published_articles.article_id = S1.article_id "
                . "JOIN article_settings S2 ON published_articles.article_id = S2.article_id "
                . "WHERE S1.setting_name = 'title' AND S2.setting_name = 'abstract' "
                . "AND published_articles.article_id = ? LIMIT 1", array($article_id));
        $row = NULL;
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
        }
        $result->Close();
        return $row;
    }
    
    public function getAuthorStatement($article_id){
        $result =& $this->retrieve("SELECT authors.author_id, authors.primary_contact, authors.seq, "
                ."authors.first_name, authors.middle_name, authors.last_name, authors.country, authors.email "
                ."FROM published_articles JOIN authors ON published_articles.article_id = "
                ."authors.submission_id WHERE published_articles.article_id = ? ORDER BY authors.seq", array($article_id));
        $return_value = array();
        while (!$result->EOF) {
                $row = $result->GetRowAssoc(false);
                $return_value[] = $row;
                $result->MoveNext();
        }
        $result->Close();
        return $return_value;
    }
    
    public function getFileStatement($article_id){
        $result =& $this->retrieve("SELECT F.file_name, F.original_file_name, F.type FROM "
                ."article_files F WHERE F.article_id = ? ORDER BY file_name", array($article_id));
        $return_value = array();
        while (!$result->EOF) {
                $row = $result->GetRowAssoc(false);
                $return_value[] = $row;
                $result->MoveNext();
        }
        $result->Close();
        return $return_value;
    }
    
    /**
     * Check article for recent packages.
     *
     * @param \int $articleId The article in question.
     * 
     * @return \bool Returns TRUE, when there was a package created in the last two days, otherwiese FALSE.
     */
    public function packageCreatedInLast2Days($articleId){
        $result = $this->getDateFilename($articleId);
        if(!array_key_exists('filename', $result)){
            return NULL;
        }
        else{
            $date = new DateTime();
            $today = $date->getTimestamp();
	    error_log('OJS - RpositoryDAO: packageCreatedIn... Wert von today: ' . $today . ' kommt da was?');
            //$lastModified = strtotime($result['date']);
             $dateOfPackage = new DateTime($result['date']);
	     $lastModified = $dateOfPackage->getTimestamp();
	     error_log('OJS - RpositoryDAO: packageCreatedIn... Wert von lastModified: ' . $lastModified . ' und was kommt hier?');
            //  2 days = 8,640,000 msec
	    if(($today - $lastModified) < 172800){
	    //if(($today - $lastModified) < 8640000){
                error_log('OJS - RpositoryDAO: packageCreatedIn... Paket angeblich jünger als zwei Tage. Hier der Wert: ' . ($today - $lastModified));
		return $result['filename'];
            }
            else{
                return NULL;
            }
        }
    }
    
    
    /**
     * Check DB for availability of file names.
     *
     * @param \string $filename The file name to check.
     * 
     * @return \bool Returns TRUE, when the file name is available, FALSE otherwise.
     */
    function fileNameAvailable($filename){
        $result =& $this->retrieve("SELECT * FROM rpository "
                ."WHERE fileName = ?", array($filename));
        if($result->EOF){
            return TRUE;
        }
        else{
            return FALSE;
        }
    }
    
    function articleIsPublished($articleId){
        $result =& $this->retrieve("SELECT * FROM published_articles "
                ."WHERE article_id = ?", array($articleId));
        $row = $result->GetRowAssoc(false);
        if($row == NULL){
            return FALSE;
        }
        else{
            if($row['date_published'] != NULL){
                return TRUE;
            }
            else{
                return FALSE;
            }                
        }
    }
    
    function updateRepository(&$plugin, $articleId, $writtenArchive){
//	$backtrace = debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	//check if an article with the given articleId exists
        $suffix='';
	$success=FALSE;
	$oldVersion = $this-> getDateFilename($articleId);
        $versionExists = FALSE;
        if(array_key_exists('filename', $oldVersion)){
            // article exists  
              $versionExists = TRUE;
        }
	$versionNumbers = $this->getMajorMinor($articleId);
	error_log('OJS - Rpository: updateRepository wird aufgerufen.');


	//delete old version and insert new version with the old name, in the case of package created in last two days
	$oldFile = $this->packageCreatedInLast2Days($articleId);
	$oldPid = array(NULL, NULL);
        if($oldFile != NULL){
            if(!unlink($plugin->getSetting(0, 'documentroot') . $plugin->getSetting(0,'path') . $oldFile)){
                error_log('OJS - rpository: error deleting file ' . $plugin->getSetting(0, 'documentroot') . $plugin->getSetting(0,'path') . $oldFile);
            }
	    $oldPid = array($this->getPidV1($articleId), $this->getPidV2($articleId));
            if(!$this->delCurrentEntry($articleId)){
                error_log('OJS - rpository: error deleting DB entry');
            }
           $success = rename($writtenArchive, $plugin->getSetting(0, 'documentroot') . $plugin->getSetting(0,'path') . $oldVersion['filename']);
	    if(!$success){
                error_log('OJS - rpository: error rewriting package to repository');
                return NULL;
            }
	    error_log('OJS - RpositoryDAO: Yeahy it works!');
            unset($success);
            $success = $this->insertNewEntry($articleId, $oldVersion['filename'], $oldPid[0], $oldPid[1], $versionNumbers['major'], $versionNumbers['minor']);
        } elseif(!$versionExists){
	 //if this is the ony version of a package, check if the name has to be changed and insert it
	//if($oldFile != NULL AND !$versionExists){
          if(!$this->fileNameAvailable(basename($writtenArchive) . "_1.0.tar.gz")){
            $suffix = 'a';
             while(!$this->fileNameAvailable(basename($writtenArchive) . $suffix . "_1.0.tar.gz")){
                if($suffix == 'z'){
                    error_log('OJS - rpository: error writing new package to repository');
                    return NULL;
                }
                ++$suffix;
             }
          }
        
          $success = rename($writtenArchive, $plugin->getSetting(0, 'documentroot') . $plugin->getSetting(0,'path') . basename($writtenArchive) . $suffix . "_1.0.tar.gz");
          if(!$success){
            error_log('OJS - rpository: error writing new package to repository');
            return NULL;
          }
          unset($success);
          $major = 1;
	  $minor = 0;
          $success = $this->insertNewEntry($articleId, basename($writtenArchive) . $suffix . "_1.0.tar.gz", $oldPid[0], $oldPid[1], $major, $minor);
       }else{
	 // if a package older than two days is edited, change the name to a newer version
         $nameBegins = preg_replace("/_1\.\d\.tar\.gz/", "", $oldVersion['filename']);
	 error_log('OJS - RpositoryDAO: Abschneiden liefert den Anfang des Paketnamens als: ' . $nameBegins);
	 //$versionNumbers = $this->getMajorMinor($articleId);
         $minorNext = strval(intval($versionNumbers['minor']) + 1);
	 $newVersionName = $nameBegins . '_' . $versionNumbers['major'] . '.' . $minorNext . '.tar.gz';        
         error_log('OJS - RpositoryDAO: was ist minorNext?: ' . $minorNext . ' und newVersionName lautet ' . $newVersionName);
	  $success = rename($writtenArchive, $plugin->getSetting(0, 'documentroot') . $plugin->getSetting(0,'path') . $newVersionName);
	  if(!$success){
             error_log('OJS - rpository: error writing EDITED VERSION of package to repository');
             return NULL;
          }
	  unset($success);
          $this->resetCurrent($articleId);
	  $success = $this->insertNewEntry($articleId, $newVersionName, $oldPid[0], $oldPid[1], $versionNumbers['major'], $minorNext);
        }

	if(!$success){
            error_log('OJS - rpository: error inserting new package into database');
            return NULL;
        }
	// TO DO: neue pid fuer neue Version

	if(!$this->hasPID($articleId)){
	// do pid stuff
	    $success = $this->updatePID($plugin, $articleId);
	    if(!$success){
		error_log("OJS - rpository: error fetching PID for archive: " . $writtenArchive);
	    }
        }
        return basename($writtenArchive) . $suffix . "_1.0.tar.gz";
    }
    
    function hasPID($articleId) {
	$result =& $this->retrieve("SELECT pidv1, pidv2 FROM rpository "
	                ."WHERE articleId = ? AND current = 1", array($articleId));
	if($result->EOF){
	   return False;
	}
	$row = $result->GetRowAssoc(false);
        if($row['pidv1'] != NULL || $row['pidv2'] != NULL){
	    return True;
	}
	return False;
    }

    function getRPackageFile($articleId){
        $result =& $this->retrieve("SELECT fileName FROM rpository "
                ."WHERE articleId = ? AND current = 1", array($articleId));
        if($result->EOF){
            return NULL;
        }
        else{
            $row = $result->GetRowAssoc();
            if($row['FILENAME'] != NULL){
                return $row['FILENAME'];
            }
            else{
                return NULL;
            }
        }
    }
	
	function getPID($articleId) {
        $pidv2 = $this->getPidV2($articleId);
        if ($pidv2 != NULL){
            return $pidv2;
            }
        else{
            return $this->getPidV1($articleId);
            }
    }
	
    function getPidV1($articleId){
        $result =& $this->retrieve("SELECT pidv1 FROM rpository "
                ."WHERE articleId = ? AND current = 1", array($articleId));
        if($result->EOF){
            return NULL;
        }
        else{
            $row = $result->GetRowAssoc(false);
            if($row['pidv1'] != NULL){
                return $row['pidv1'];
            }
            else{
                return NULL;
            }
        }
    }
    
    function getPidV2($articleId){
        $result =& $this->retrieve("SELECT pidv2 FROM rpository "
                ."WHERE articleId = ? AND current = 1", array($articleId));
        if($result->EOF){
            return NULL;
        }
        else{
            $row = $result->GetRowAssoc(false);
            if($row['pidv2'] != NULL){
                return $row['pidv2'];
            }
            else{
                return NULL;
            }
        }
    }
    
    public function getPackageName($article_id){
        $result = $this->retrieve("SELECT fileName FROM rpository " .
                "WHERE articleId = ? AND current = 1", array($article_id));
        $row = NULL;
        if($result->EOF){
            return NULL;
        }
        else{            
            $row = $result->GetRowAssoc(false);
            $result->Close();
            return $row['filename'];
        }
    }
    
    public function getAuthors($submissionId){
        $result = $this->retrieve("SELECT * FROM authors " .
                "WHERE submission_id = ?", array($submissionId));
        $authors = '';
        while(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            //print_r($row);
            $authors .= $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ', ';
            $result->MoveNext();
        }
        $authors = rtrim($authors, ', ');
        return $authors;
    }
    
    public function getDate($articleId){
        $result = $this->retrieve("SELECT date_published FROM published_articles " .
                "WHERE article_id = ? LIMIT 1", array($articleId));
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            return $row['date_published'];
        }
        else{
            return NULL;
        }
    }
    
    function fetchPIDlegacy(&$plugin, $articleId){
        $daos               =& DAORegistry::getDAOs();
        $articleDao         =& $daos['ArticleDAO'];
        
        $url = $this->getPackageName($articleId);
        if($url == ''){
	    error_log("OJS - rpository: getPackageName failed");
            return NULL;
        }
        $fileSize = filesize($plugin->getSetting(0, 'documentroot') . $plugin->getSetting(0,'path') . $url);
        if($fileSize == NULL){
 	    error_log("couldn't get file size");
            return NULL;
        }        
        if($url == NULL){
            error_log("url = null");
            return NULL;
        }
        else{
	        $url = $plugin->getSetting(0, 'hostname') . "/index.php/mr2/oai?verb=GetRecord&metadataPrefix=mods&identifier=oai:ojs." .$plugin->getSetting(0,'hostname'). ":article/" . $articleId;
            
        }
                
        $article = $articleDao->getArticle($articleId);
        $title = $article->getArticleTitle();
        if($title == ''){
	    error_log("couldn't get title");
            return NULL;
        }
        
        $submissionId = $article->_data['id'];
        $authors = $this->getAuthors($submissionId);
        if($authors == ''){
	    error_log("couldn't get authors");
            return NULL;
        }
        
        $date = $this->getDate($articleId);
        if($date == ''){
            error_log("couldn't get publish date");
            return NULL;
        }
        
        // set POST variables
        
        $fields = array('url' => urlencode($url),
            'size' => urlencode($fileSize),
            'title' => urlencode($title),
            'authors' => urlencode($authors),
            'pubdate' => urlencode($date),
            'encoding' => urlencode('xml'));
        $fields_string = '';
        //url-ify the data for the POST
        foreach($fields as $key=>$value){
            $fields_string .= $key.'='.$value.'&';
        }        
        $fields_string = rtrim($fields_string, '&');
        
        // curl stuff
        $ch = curl_init();        
        //curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_URL, $plugin->getSetting(0, 'pidv1_service_url'));
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, $plugin->getSetting(0, 'pidv1_timeout'));
        curl_setopt($ch, CURLOPT_USERPWD, $plugin->getSetting(0, 'pidv1_user') . ":" . $plugin->getSetting(0, 'pidv1_pw'));
        $result = curl_exec($ch);
        curl_close($ch);
        
        // newlines messed up the xml parser - removing
        $result = str_replace("\\r\\n", '', $result);
        if($result == NULL){
            error_log("OJS - rpository: creating PID failed: no response from PID server");
            return NULL;
        }
        elseif(substr($result, 0, 6)== '<html>'){
            $m = array();
            preg_match_all("/<h1>HTTP Status 403 - Another Handle \/ PID already points here: ([A-F0-9-\/]*)<\/h1>/", $result, $m );
            if($m[1] == ''){
                error_log("OJS - rpository: fetching PID failed: " . $e->getMessage());
                return NULL;
            }
            else{
                //error_log(print_r($m, TRUE));
                return $m[1][0];
            }
        }
        
        
        
        try{
            $xml = new SimpleXMLElement($result);
        }
        catch(Exception $e){
            error_log("OJS - rpository: unexpected PID v1 server response");
            return NULL;
        }
        $out = $xml->Handle->pid;
        
        return (String)$out;
    }
    //http://asvsp.informatik.uni-leipzig.de/index.php/mr2/oai?verb=GetRecord&metadataPrefix=mods&identifier=oai:ojs.asvsp.informatik.uni-leipzig.de:article/2
    function fetchPID(&$plugin, $articleId){
        $url = $this->getPackageName($articleId);
        if($url == ''){
            return NULL;
        }
        $url = $plugin->getSetting(0, 'hostname') . "/index.php/mr2/oai?verb=GetRecord&metadataPrefix=mods&identifier=oai:ojs." .$plugin->getSetting(0,'hostname'). ":article/" . $articleId;

        $data = '[{"type":"URL","parsed_data":"' . $url . '"}]';
        $ch = curl_init();
        //error_log("url: " . $url);
        //error_log("pidv2_service_url: " . $plugin->getSetting(0, 'pidv2_service_url') . $plugin->getSetting(0, 'pidv2_prefix') );
        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $plugin->getSetting(0, 'pidv2_service_url') . $plugin->getSetting(0, 'pidv2_prefix'));
        curl_setopt($ch,CURLOPT_USERPWD, $plugin->getSetting(0, 'pidv2_user') .":".$plugin->getSetting(0, 'pidv2_pw'));
        curl_setopt($ch, CURLOPT_TIMEOUT, $plugin->getSetting(0, 'pidv2_timeout'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept:application/json', 'Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        curl_close($ch);
        //error_log(print_r($result, TRUE));
        $m = array();
        preg_match_all('/<dd><a href="([A-F0-9-]*)">/', $result, $m );
        //error_log(print_r($m, TRUE));
        if($m[1] == ''){
            return NULL;
        }
        else{
            return $plugin->getSetting(0, 'pidv2_prefix'). "/" . $m[1][0];
        }
    }
    
    function updatePIDv1($article_id, $pid){     
        return $this->update("Insert into rpository_unused_pid( Select * from rpository Where articleID= ?  AND current = 1); UPDATE rpository SET pidv1=? WHERE articleId=? AND current=1", array($article_id,$pid, $article_id));        
    }
    
    function updatePIDv2($article_id, $pid){     
        return $this->update("Insert into rpository_unused_pid( Select * from rpository Where articleID= ?  AND current = 1); UPDATE rpository SET pidv2=? WHERE articleId=? AND current=1", array($article_id,$pid, $article_id));        
    }
    
    function updatePID(&$plugin, $articleId){
        if($plugin->getSetting(0, 'pidstatus') == 0){
            return TRUE;
        }
        
        if($plugin->getSetting(0, 'pidstatus') == 1){
            if($plugin->getSetting(0, 'pidv1_pw') == "" || $plugin->getSetting(0, 'pidv1_user') == ""){
                error_log("PIDv1_User and PIDv1_Passwd need to be filled in.");
                return FALSE;
            }
            //template has been filled with username & password
            $pidv1 = $this->fetchPIDlegacy($plugin, $articleId);
            if($pidv1 == NULL){
                error_log("OJS - rpository: error fetching pidv1");
                return FALSE;
            }
            else{
                $this->updatePIDv1($articleId, $pidv1);
            }
        }
        
        if($plugin->getSetting(0, 'pidstatus') == 2){
            if($plugin->getSetting(0, 'pidv2_pw') == "" || $plugin->getSetting(0, 'pidv2_user')== ""){
                error_log("PIDv2_User and PIDv2_Passwd need to be filled in.");
                return FALSE;
            }
           //template has been filled with username & password
           $pidv2 = $this->fetchPID($plugin, $articleId);
           if($pidv2 == NULL){
               error_log("OJS - rpository: error fetching pidv2");
               return FALSE;
           }
           else{
               $this->updatePIDv2($articleId, $pidv2);
           }
        }
        return TRUE;
    }
    
    function getArticlesWithoutPid($pid_version){
        switch($pid_version){
            case 1:
                $result = $this->retrieve("select article_id from published_articles JOIN rpository ON published_articles.article_id=rpository.articleId WHERE pidv1 IS NULL AND date_published IS NOT NULL", array());
                $out_array=array();
                while(!$result->EOF){
                    $row = $result->GetRowAssoc(false);
                    $out_array[] = $row['article_id'];
                    $result->MoveNext();
                }
                $result->Close();
                return $out_array;
                break;
            case 2:
                $result = $this->retrieve("select article_id from published_articles JOIN rpository ON published_articles.article_id=rpository.articleId WHERE pidv2 IS NULL AND date_published IS NOT NULL", array());
                $out_array=array();
                while(!$result->EOF){
                    $row = $result->GetRowAssoc(false);
                    $out_array[] = $row['article_id'];
                    $result->MoveNext();
                }
                $result->Close();
                return $out_array;
                break;
            default:
                return NULL;
                break;
        }
    }

    function resetCurrent($article_id){
    //current vom alten Paket auf NUll, vom neuen auf 1
     $xyz = $this->update('UPDATE rpository SET current = 0 WHERE articleId=?', $article_id);       
    }
   
    function getMajorMinor($article_id){
   //die Werte von major und minor holen und als array zurück geben.
       $result =& $this->retrieve('SELECT major, minor FROM rpository WHERE articleId = ? AND current = 1', array($article_id));
       $return_value = array();
          if(!$result->EOF){
               $row = $result->GetRowAssoc(false);

               $return_value['major']   = $row['major'];
               $return_value['minor']   = $row['minor'];
           }
        $result->Close();
        return $return_value;
    
    }    
/*
   function &_returnArticleFileFromRow(&$row) {
     $articleFile = new ArticleFile();
     $articleFile->setFileId($row['file_id']);
     $articleFile->setSourceFileId($row['source_file_id']);
     $articleFile->setSourceRevision($row['source_revision']);
     $articleFile->setRevision($row['revision']);
     $articleFile->setArticleId($row['article_id']);
     $articleFile->setFileName($row['file_name']);
     $articleFile->setFileType($row['file_type']);
     $articleFile->setFileSize($row['file_size']);
     $articleFile->setOriginalFileName($row['original_file_name']);
     $articleFile->setFileStage($row['file_stage']);
     $articleFile->setAssocId($row['assoc_id']);
     $articleFile->setDateUploaded($this->datetimeFromDB($row['date_uploaded']));
     $articleFile->setDateModified($this->datetimeFromDB($row['date_modified']));
     $articleFile->setRound($row['round']);
     $articleFile->setViewable($row['viewable']);
     HookRegistry::call('ArticleFileDAO::_returnArticleFileFromRow', array(&$articleFile, &$row));
     return $articleFile;
   }*/



    function getAllPackagesByArticle($article_id){
      // ein array von Einträgen
      $result =& $this->retrieve('SELECT * FROM rpository WHERE articleId = ?', array($article_id));
      $packages = array();
      //$n = 0;
       while (!$result->EOF) {
        // error_log('OJS - RpositoryDAO: ein Paket wird in ein Array gesteckt unter der Nummer: ' . $n);
	 $row = $result->GetRowAssoc(false);
	 // $packages[] =& $this->_returnArticleFileFromRow($result->GetRowAssoc(false));
         $minor = $row['minor'];
	 $packages[$minor]['date']  = $row['date'];
         $packages[$minor]['filename']   = $row['filename'];
	 $packages[$minor]['minor']   = $row['minor'];
	 $packages[$minor]['current']   = $row['current'];
	 $packages[$minor]['pidv1']   = $row['pidv1'];
	 $packages[$minor]['pidv2']   = $row['pidv2'];
	 $packages[$minor]['packageid']   = $row['packageid'];
	 //$n++;
	 $result->moveNext();
       }
       
        $result->Close();
       unset($result);
       
       return $packages;
    }



}
?>