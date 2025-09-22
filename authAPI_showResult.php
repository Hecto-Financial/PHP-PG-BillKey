<?php
include_once "config.php";
include_once "settleUtils.php";

//config.php에서 설정 정보 가져오기
$aesKey = AES256_KEY;           //AES256 암복호화 키
$licenseKey = LICENSE_KEY;      //라이센스 키
$serverURL = SERVER_URL;        //타겟URL
$connTimeout = CONN_TIMEOUT;    //connect timeout
$timeout = TIMEOUT;             //curl total timeout

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
    "pktHash"   => null_to_empty(get_param("pktHash")),     //해쉬값
    "cardNo"    => null_to_empty(get_param("cardNo")),      //카드번호
    "idntNo"    => null_to_empty(get_param("idntNo")),      //식별번호
    "vldDtMon"  => null_to_empty(get_param("vldDtMon")),    //유효기간(월)
    "vldDtYear" => null_to_empty(get_param("vldDtYear")),   //유효기간(년)
    "cardPwd"   => null_to_empty(get_param("cardPwd")),     //카드비밀번호
    "mchtCustNm"=> null_to_empty(get_param("mchtCustNm")),  //고객명
    "mchtCustId"=> null_to_empty(get_param("mchtCustId")),  //고객아이디
    "keyRegYn"  => null_to_empty(get_param("keyRegYn")),    //빌키발급요청여부
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
    "trdDt" => "",      //거래일자
    "trdTm" => "",      //거래시간
    "outStatCd" => "",  //거래상태코드
    "outRsltCd" => "",  //결과코드
    "outRsltMsg" => ""  //결과메세지
);


//응답 파라미터(바디)
$RES_BODY = array(
    "pktHash" => "",        //해쉬값
    "cardNo" => "",         //카드번호
    "issrId" => "",         //발급사아이디
    "cardNm" => "",         //카드사명
    "cardKind" => "",       //카드종류명
    "billKey" => "",        //빌키
);



//AES256 암호화 처리 될 파라미터
$ENCRYPT_PARAMS = array("cardNo", "idntNo", "vldDtMon", "vldDtYear", "cardPwd");


// //AES256 복호화 필요 파라미터
// $DECRYPT_PARAMS = array("cardNo");



/** ===============================================================================================
 *                          SHA256 해쉬 처리
 *  조합필드 : 요청일자 + 요청시간 + 상점아이디 + 상점주문번호 + "0" + 라이센스키
 *  ===============================================================================================   */
$hashPlain =  $REQ_HEADER["trdDt"].$REQ_HEADER["trdTm"].$REQ_HEADER["mchtId"].$REQ_HEADER["mchtTrdNo"]."0".$licenseKey;
log_message(LOG_FILE, "HMAC_HASH plainText[".$hashPlain."]");
$REQ_BODY["pktHash"] = hash("sha256", $hashPlain);



/** =======================================================================
 *                          AES256 암호화 처리
 *  =======================================================================  */
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
$requestUrl = $serverURL."/spay/APICardAuth.do";


//요청파라미터 JSON에 세팅
//params, data 이름은 파이낸셜로 전달되야 하는 값이니 변경하지 마십시오.
$reqParam = array(
    "params" => $REQ_HEADER,
    "data" => $REQ_BODY
);

/** ===============================================================================================
 *                              API호출(가맹점->파이낸셜) 및 응답 처리
 *  ===============================================================================================   */
$respParam = array();


//send_api ( API호출 URL, 전송될데이터, 연결 타임아웃, curl 타임아웃 )
$resData = send_api($requestUrl, $reqParam, $connTimeout, $timeout); 

//응답 파라미터 파싱
$resData = json_decode( $resData, true );
$respHeader =   array_key_exists('params', $resData) ? $resData['params'] : null;
$respBody =  array_key_exists('data', $resData) ? $resData['data'] : null;

//응답 파라미터 세팅(params)
if( $respHeader != null ){
    foreach ($RES_HEADER as $key => $val ) {
        $respParam[$key] =  null_to_empty( array_key_exists($key, $respHeader) ? $respHeader[$key] : "" );
    }
}else{
    foreach ($RES_HEADER as $key => $val ) {
        $respParam[$key] =  "";
    }
}

//응답 파라미터 세팅(data)
if( $respBody != null){
    foreach ($RES_BODY as $key => $val ) {
        $respParam[$key] =  null_to_empty( array_key_exists($key, $respBody) ? $respBody[$key] : "" );
    }
}else{
    foreach ($RES_BODY as $key => $val ) {
        $respParam[$key] =  "";
    }
}



// /** ======================================================================
//  *                          AES256 복호화 처리
//  *  ====================================================================== */
// try{
//     foreach($DECRYPT_PARAMS as $i){
//         if( array_key_exists($i, $respParam)){
//             $aesCipher = trim($respParam[$i]);
//             if( "" != $aesCipher ){
//                 $cipherRaw = base64_decode($aesCipher);
//                 if( $cipherRaw === false ){
//                     throw new Exception("base64_decode() error ".$i."[".$aesCipher."]");
//                 }

//                 $aesPlain = openssl_decrypt($cipherRaw, "AES-256-ECB",  $aesKey , OPENSSL_RAW_DATA);

//                 if( $aesPlain === false ){
//                     throw new Exception("openssl_decrypt() error ".$i."[".$aesCipher."]");
//                 }

//                 $respParam[$i] = $aesPlain;//복호화된 데이터로 세팅
//                 log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][AES256 Decrypt] ".$i."[".$aesCipher."] ---> [".$aesPlain."]");
//             }
//         }
//     }
// }catch(Exception $ex){
//     log_message(LOG_FILE, "[".$REQ_HEADER["mchtTrdNo"]."][AES256 Decrypt] AES256 Decrypt Fail! : ".$ex->getMessage());
// }

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>S'Pay</title>
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
            <td><?php echo $respParam["mchtId"] ?></td>
        </tr>
        <tr>
            <td>ver[버전]</td>
            <td><?php echo $respParam["ver"] ?></td>
        </tr>
        <tr>
            <td>method[결제수단]</td>
            <td><?php echo $respParam["method"] ?></td>
        </tr>
        <tr>
            <td>bizType[업무구분]</td>
            <td><?php echo $respParam["bizType"] ?></td>
        </tr>
        <tr>
            <td>encCd[암호화구분]</td>
            <td><?php echo $respParam["encCd"] ?></td>
        </tr>
        <tr>
            <td>mchtTrdNo[상점주문번호]</td>
            <td><?php echo $respParam["mchtTrdNo"] ?></td>
        </tr>
        <tr>
            <td>trdNo[헥토파이낸셜 거래번호]</td>
            <td><?php echo $respParam["trdNo"] ?></td>
        </tr>
        <tr>
            <td>trdDt[거래일자]</td>
            <td><?php echo $respParam["trdDt"] ?></td>
        </tr>
        <tr>
            <td>trdTm[거래시간]</td>
            <td><?php echo $respParam["trdTm"] ?></td>
        </tr>
        <tr>
            <td>outStatCd[거래상태코드]</td>
            <td><?php echo $respParam["outStatCd"] ?></td>
        </tr>
        <tr>
            <td>outRsltCd[거래결과코드]</td>
            <td><?php echo $respParam["outRsltCd"] ?></td>
        </tr>
        <tr>
            <td>outRsltMsg[결과메세지]</td>
            <td><?php echo $respParam["outRsltMsg"] ?></td>
        </tr>
     	<tr>
            <td colspan="2" style="text-align: center;"><h4>data</h4></td>
        </tr>
        <tr>
            <td>pktHash[해쉬값]</td>
            <td><?php echo $respParam["pktHash"] ?></td>
        </tr>
        <tr>
        <tr>
            <td>cardNo[카드번호]</td>
            <td><?php echo $respParam["cardNo"] ?></td>
        </tr>
        <tr>
            <td>issrId[발급사아이디]</td>
            <td><?php echo $respParam["issrId"] ?></td>
        </tr>
        <tr>
            <td>cardNm[카드사명]</td>
            <td><?php echo $respParam["cardNm"] ?></td>
        </tr>
        <tr>
            <td>cardKind[카드종류명]</td>
            <td><?php echo $respParam["cardKind"] ?></td>
        </tr>
        <tr style="background-color:yellow;">
            <td>billKey[빌키]</td>
            <td><?php echo $respParam["billKey"] ?></td>
        </tr>

        <tr>
            <td colspan="2" style="text-align: center;">
            <input type="button" value="돌아가기" style="margin-top:20px;" onclick="location.href='authAPI_form.php'">
            </td>
        </tr>
    </table>
</div>
</body>
</html>

