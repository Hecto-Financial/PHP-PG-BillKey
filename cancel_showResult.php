<?php
include_once "config.php";
include_once "settleUtils.php";

//설정 정보 가져오기
$aesKey = AES256_KEY;       //AES256 암복호화 키
$licenseKey = LICENSE_KEY;  //라이센스 키
$serverURL = SERVER_URL;    //타겟URL
$connTimeout = CONN_TIMEOUT;//connect timeout
$timeout = TIMEOUT;         //curl total timeout

//요청 파라미터(헤더)
$REQ_HEADER = array(
    "mchtId"    => null_to_empty(get_param("mchtId")),      //상점아이디
    "ver"       => null_to_empty(get_param("ver")),         //버전
    "method"    => null_to_empty(get_param("method")),      //결제수단
    "bizType"   => null_to_empty(get_param("bizType")),     //업무구분
    "encCd"     => null_to_empty(get_param("encCd")),       //암호화구분
    "mchtTrdNo" => null_to_empty(get_param("mchtTrdNo")),   //상점주문번호
    "trdDt"     => null_to_empty(get_param("trdDt")),       //요청일자
    "trdTm"     => null_to_empty(get_param("trdTm")),       //요청시간
    "mobileYn"  => null_to_empty(get_param("mobileYn")),    //모바일여부
    "osType"    => null_to_empty(get_param("osType"))       //운영체제 구분
);


//요청 파라미터(바디)
$REQ_BODY = array(
    "orgTrdNo"  => null_to_empty(get_param("orgTrdNo")),    //원거래번호
    "cnclAmt"   => null_to_empty(get_param("cnclAmt")),     //취소금액
    "crcCd"     => null_to_empty(get_param("crcCd")),       //통화구분
    "cnclOrd"   => null_to_empty(get_param("cnclOrd")),     //부분취소회차
    "cnclRsn"   => null_to_empty(get_param("cnclRsn")),     //취소사유
    "taxTypeCd" => null_to_empty(get_param("taxTypeCd")),   //면세여부
    "taxAmt"    => null_to_empty(get_param("taxAmt")),      //과세금액
    "vatAmt"    => null_to_empty(get_param("vatAmt")),      //부가세금액
    "taxFreeAmt"=> null_to_empty(get_param("taxFreeAmt")),  //비과세금액
    "svcAmt"    => null_to_empty(get_param("svcAmt")),      //봉사료
);


//응답 파라미터(헤더)
$RES_HEADER = array(
    "mchtId" => "",     //상점아이디
    "ver" => "",        //버전
    "method" => "",     //결제수단
    "bizType" => "",    //업무구분
    "encCd" => "",      //암호화구분
    "mchtTrdNo" => "",  //상점주문번호
    "trdNo" => "",      //헥토파이낸셜거래번호
    "trdDt" => "",      //요청일자
    "trdTm" => "",      //요청시간
    "outStatCd" => "",  //결과코드
    "outRsltCd" => "",  //거절코드
    "outRsltMsg" => ""  //결과메세지
);


//응답 파라미터(바디)
$RES_BODY = array(
    "pktHash" => "",        //해쉬값
    "orgTrdNo" => "",       //원거래번호
    "cnclAmt" => "",        //취소금액
    "blcAmt" => ""          //취소가능잔액
);


//AES256 암호화 처리 될 파라미터
$ENCRYPT_PARAMS = array("cnclAmt","taxAmt","vatAmt","taxFreeAmt", "svcAmt");


//AES256 복호화 필요 파라미터
$DECRYPT_PARAMS = array("cnclAmt", "blcAmt");



/** ======================================================================================================
 *                          SHA256 해쉬 처리
 *  조합필드 : 요청일자 + 요청시간 + 상점아이디 + 상점주문번호 + 취소금액(평문) + 라이센스키
 *  ======================================================================================================   */
$hashPlain = "";
$hashCipher ="";
try{
    $hashPlain = $REQ_HEADER["trdDt"].$REQ_HEADER["trdTm"].$REQ_HEADER["mchtId"].$REQ_HEADER["mchtTrdNo"].$REQ_BODY["cnclAmt"].$licenseKey;
    $hashCipher = hash("sha256", $hashPlain);
}catch(Exception $ex){
    log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][SHA256 HASHING] Hashing Fail! : ".$ex->getMessage());
}finally{
    log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][SHA256 HASHING] Plain Text[".$hashPlain."] ---> Cipher Text[".$hashCipher."]");
    $REQ_BODY["pktHash"] = $hashCipher; // sha256 해쉬 결과 저장
}



/** ======================================================================
 *                          AES256 암호화 처리
 *  ======================================================================   */
try{
    foreach($ENCRYPT_PARAMS as $i){
        $aesPlain = $REQ_BODY[$i];
        if( !( "" == $aesPlain )){
            
            $chiperRaw = openssl_encrypt($aesPlain, "AES-256-ECB",  $aesKey , OPENSSL_RAW_DATA);
            $aesCipher = base64_encode($chiperRaw);

            $REQ_BODY[$i] = $aesCipher;//암호화 결과 값 세팅
            log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][AES256 Encrypt] ".$i."[".$aesPlain."] ---> [".$aesCipher."]");
        }
    }
}catch(Exception $ex){
    log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][AES256 Encrypt] AES256 Fail! : ".$ex->getMessage());
    throw new Exception("aes256 encrypt fail");
}

//URL설정
$requestUrl = $serverURL."/spay/APICancel.do";

//요청파라미터 JSON에 세팅
//params, data 이름은 헥토파이낸셜로 전달되야 하는 값이니 변경하지 마십시오.
$reqParam = array(
    "params" => $REQ_HEADER,
    "data" => $REQ_BODY
);


/** =====================================================================================
 *                          API호출(가맹점->헥토파이낸셜) 및 응답 처리
 *  =====================================================================================  */
$respParam = array();


//send_api ( API호출 URL, 전송될데이터, 연결 타임아웃, curl 타임아웃 )
$resData = send_api($requestUrl, $reqParam, $connTimeout, $timeout); 

//응답 파라미터 파싱
$resData = json_decode( $resData, true );
$respHeader =   array_key_exists('params', $resData) ? $resData['params'] : null;
$respBody =  array_key_exists('data', $resData) ? $resData['data'] : null;

//응답 파라미터 세팅(헤더)
if( $respHeader != null ){
    foreach ($RES_HEADER as $key => $val ) {
        $respParam[$key] =  null_to_empty( array_key_exists($key, $respHeader) ? $respHeader[$key] : "" );
    }
}else{
    foreach ($RES_HEADER as $key => $val ) {
        $respParam[$key] =  "";
    }
}

//응답 파라미터 세팅(바디)
if( $respBody != null){
    foreach ($RES_BODY as $key => $val ) {
        $respParam[$key] =  null_to_empty( array_key_exists($key, $respBody) ? $respBody[$key] : "" );
    }
}else{
    foreach ($RES_BODY as $key => $val ) {
        $respParam[$key] =  "";
    }
}



/** ======================================================================
 *                          AES256 복호화 처리
 *  ====================================================================== */
try{
    foreach($DECRYPT_PARAMS as $i){
        if( array_key_exists($i, $respParam)){
            $aesCipher = trim($respParam[$i]);
            if( "" != $aesCipher ){
                $cipherRaw = base64_decode($aesCipher);
                if( $cipherRaw === false ){
                    throw new Exception("base64_decode() error ".$i."[".$aesCipher."]");
                }

                $aesPlain = openssl_decrypt($cipherRaw, "AES-256-ECB",  $aesKey , OPENSSL_RAW_DATA);

                if( $aesPlain === false ){
                    throw new Exception("openssl_decrypt() error ".$i."[".$aesCipher."]");
                }

                $respParam[$i] = $aesPlain;//복호화된 데이터로 세팅
                log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][AES256 Decrypt] ".$i."[".$aesCipher."] ---> [".$aesPlain."]");
            }
        }
    }
}catch(Exception $ex){
    log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][AES256 Decrypt] AES256 Decrypt Fail! : ".$ex->getMessage());
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>헥토파이낸셜</title>
<style type="text/css">
#STPG_RSLT		{font-family:굴림; font-size:10pt;}
#STPG_RSLT h4	{background-color:#f1f1f1;padding:4px;margin:2px;}
</style>
</head>
<body>
<h3>응답 결과</h3>
<div id="STPG_RSLT">
    <table>
    <tr>
		<td colspan="2" style="text-align: center;"><h4>params</h4></td>
	</tr>
    <tr>
        <td>mchtId[상점아이디]</td>
        <td><?php echo htmlspecialchars($respParam["mchtId"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>ver[버전]</td>
        <td><?php echo htmlspecialchars($respParam["ver"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>method[결제수단]</td>
        <td><?php echo htmlspecialchars($respParam["method"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>bizType[업무구분]</td>
        <td><?php echo htmlspecialchars($respParam["bizType"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>encCd[암호화구분]</td>
        <td><?php echo htmlspecialchars($respParam["encCd"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>mchtTrdNo[상점주문번호]</td>
        <td><?php echo htmlspecialchars($respParam["mchtTrdNo"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>trdNo[헥토파이낸셜 거래번호]</td>
        <td><?php echo htmlspecialchars($respParam["trdNo"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>trdDt[취소요청일자]</td>
        <td><?php echo htmlspecialchars($respParam["trdDt"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>trdTm[취소요청시간]</td>
        <td><?php echo htmlspecialchars($respParam["trdTm"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>outStatCd[거래상태코드]</td>
        <td><?php echo htmlspecialchars($respParam["outStatCd"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>outRsltCd[거래결과코드]</td>
        <td><?php echo htmlspecialchars($respParam["outRsltCd"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>outRsltMsg[결과메세지]</td>
        <td><?php echo htmlspecialchars($respParam["outRsltMsg"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: center;"><h4>data</h4></td>
    </tr>
    <tr>
        <td>pktHash[해쉬값]</td>
        <td><?php echo htmlspecialchars($respParam["pktHash"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>orgTrdNo[원거래번호]</td>
        <td><?php echo htmlspecialchars($respParam["orgTrdNo"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>cnclAmt[취소금액]</td>
        <td><?php echo htmlspecialchars($respParam["cnclAmt"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td>blcAmt[취소가능잔액]</td> 
        <td><?php echo htmlspecialchars($respParam["blcAmt"], ENT_QUOTES, "UTF-8") ?></td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: center;"><input style="margin-top:20px;" type="button" value="돌아가기" onclick="location.href='cancel_form.php'"></td>
    </tr>
    </table>
</div>
</body>
</html>