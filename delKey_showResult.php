<?php
include_once "config.php";
include_once "settleUtils.php";

//config.php에서 설정 정보 가져오기
$licenseKey = LICENSE_KEY;      //라이센스 키
$serverURL = SERVER_URL;        //타겟URL
$connTimeout = CONN_TIMEOUT;    //connect timeout
$timeout = TIMEOUT;             //curl total timeout

//요청 파라미터(헤더)
$REQ_HEADER = array(
    "mchtId"    => null_to_empty(get_param("mchtId")),      //상점아이디
    "ver"       => null_to_empty(get_param("ver")),         //버전
    "method"    => null_to_empty(get_param("method")),      //결제수단
    "bizType"   => null_to_empty(get_param("bizType")),     //업무구분(A1:빌키 삭제)
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
    "billKey"   => null_to_empty(get_param("billKey")),     //삭제할 빌키(평문 전송, 암호화 대상 아님)
    "etcInfo"   => null_to_empty(get_param("etcInfo"))      //해지사유코드(선택)
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
    "pktHash" => "",    //해쉬값
    "billKey" => ""     //삭제된 빌키
);


/** ===============================================================================================
 *                          SHA256 해쉬 처리
 *  조합필드 : 요청일자 + 요청시간 + 상점아이디 + 상점주문번호 + "0" + 라이센스키
 *  ※ 빌키 삭제는 거래금액이 없으므로 금액 자리에 반드시 "0"(문자)을 사용합니다.
 *  ===============================================================================================   */
$hashPlain =  $REQ_HEADER["trdDt"].$REQ_HEADER["trdTm"].$REQ_HEADER["mchtId"].$REQ_HEADER["mchtTrdNo"]."0".$licenseKey;
log_message(LOG_FILE, "HMAC_HASH plainText[".$hashPlain."]");
$REQ_BODY["pktHash"] = hash("sha256", $hashPlain);


/** =======================================================================
 *                          AES256 암호화 처리
 *  빌키 삭제는 암호화 대상 파라미터가 없습니다.
 *  빌키(billKey)는 평문으로 전송합니다.
 *  =======================================================================  */


//URL설정
$requestUrl = $serverURL."/spay/APICardActionDelkey.do";


//요청파라미터 JSON에 세팅
//params, data 이름은 헥토파이낸셜로 전달되야 하는 값이니 변경하지 마십시오.
$reqParam = array(
    "params" => $REQ_HEADER,
    "data" => $REQ_BODY
);

/** ===============================================================================================
 *                              API호출(가맹점->헥토파이낸셜) 및 응답 처리
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

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>헥토파이낸셜 PG 빌키 샘플</title>
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
            <td>trdDt[거래일자]</td>
            <td><?php echo htmlspecialchars($respParam["trdDt"], ENT_QUOTES, "UTF-8") ?></td>
        </tr>
        <tr>
            <td>trdTm[거래시간]</td>
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
            <td>billKey[삭제된 빌키]</td>
            <td><?php echo htmlspecialchars($respParam["billKey"], ENT_QUOTES, "UTF-8") ?></td>
        </tr>
    </table>
</div>
</body>
</html>
