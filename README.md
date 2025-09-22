# PHP-PG-BillKey

헥토파이낸셜 신용카드 비/구인증(빌키) API 연동을 위한 PHP 샘플 코드입니다.

## 📋 개요

본 샘플코드는 **API 직접 호출(Non-UI) 방식**이며, 결제창(UI) 방식이 아닙니다.
- 결제창(UI) 방식을 원하실 경우, 표준결제창 연동하시면 됩니다.
- 1회차는 Non-UI 또는 UI 방식으로 취사선택하여 결제하시면 됩니다.
- 2회차 결제는 발급받으신 빌키로 API 직접 호출하여 결제하시면 됩니다.

## 파일 구조

```
/(Project Root Directory)
     | 
    │  index.html			<--- index페이지
    │  config.php			<--- 기본정보 설정파일(*자사에 맞게 변경 필요)
     |   settleUtils.php			<--- 유틸성 함수가 선언된 페이지
     | 
    │  pay_form.php			<--- 결제 API 양식
    │  billKey_form.php		<--- 빌키 결제 API 양식
    │  pay_showResult.php		<--- 결제 처리 및 결과 화면
     | 
    │  authAPI_form.php		<--- 빌키 발급 API 양식
    │  authAPI_showResult.php		<--- 빌키 발급 및 결과 출력
     |	
    │  cancel_form.php		<--- 취소 메인 폼
    │  cancel_showResult.php		<--- 취소 처리 및 결과 화면
     | 
    │  receiveNoti.php		<--- 결제 완료 후 노티 수신 페이지
    │  processNoti.php		<--- 노티 수신 후 처리하는 페이지
     | 
```

## 📄 파일 설명

### 🔧 공통 페이지
- **config.php**: 상점아이디, 암복호화키 등을 설정할 수 있는 설정 파일입니다.
- **settleUtils.php**: 유틸성 함수가 선언된 페이지입니다. PHP에 curl 패키지와 openssl 패키지가 설치되어 있어야 정상 작동합니다.
- **receiveNoti.php**: 결제 또는 취소 처리가 완료된 후, 헥토파이낸셜에서 가맹점으로 전달하는 노티(결과통보)를 수신하는 페이지입니다.
- **processNoti.php**: receiveNoti.php에서 결제 또는 취소의 성공/실패에 따라 적절한 로직을 수행하는 메소드를 정의한 파일입니다.

### 💳 결제 관련 페이지
- **pay_form.php**: 결제 API 양식으로서, 빌키서비스상점의 경우 응답으로 빌키가 발급됩니다.
- **billKey_form.php**: 발급받은 빌키로 결제하는 API 양식입니다.
- **pay_showResult.php**: 헥토파이낸셜과 Server to Server로 커넥션하여, 결제 요청을 하고 응답을 받아 결과를 출력하는 페이지입니다.
- **authAPI_form.php**: 빌키 발급 API 양식으로서, 결제하지 않고 응답으로 빌키가 발급됩니다.
- **authAPI_showResult.php**: 헥토파이낸셜과 Server to Server로 커넥션하여, 요청을 하고 응답을 받아 결과를 출력하는 페이지입니다.

### ❌ 취소 관련 페이지
- **cancel_form.php**: 취소 요청 시 사용자로부터 정보를 입력받는 Form 페이지입니다.
- **cancel_showResult.php**: 헥토파이낸셜과 Server to Server로 커넥션하여, 취소 요청을 하고 응답을 받아 결과를 출력하는 페이지입니다.

## 🔄 페이지 처리 순서

- **결제 API(빌키 발급 포함)**: pay_form.php → pay_showResult.php
- **빌키 결제 API**: billKey_form.php → pay_showResult.php
- **빌키 발급 API**: authAPI_form.php → authAPI_showResult.php
- **취소 처리 순서**: cancel_form.php → cancel_showResult.php
- **노티 처리 순서**: receiveNoti.php → processNoti.php

## ⚙️ config.php 설정 파일 변수 설명

- **PG_MID**: 상점아이디. 테스트환경에서의 상점아이디는 샘플소스에 기재되어 있습니다. 상용테스트 시에는 헥토파이낸셜에서 발급한 MID로 설정하셔야 합니다. 이 값은 외부에 노출되어서는 안됩니다.
- **LICENSE_KEY**: MID당 하나의 라이센스키가 발급됩니다. SHA256 해시체크 용도로 사용됩니다. 이 값은 외부에 노출되어서는 안됩니다.
- **AES256_KEY**: 개인정보/민감정보를 암복호화하는데 사용되는 키로서, 외부에 노출되어서는 안됩니다.
- **SERVER_URL**: 헥토파이낸셜 API 통신 타겟 URL입니다.
- **CONN_TIMEOUT**: 헥토파이낸셜 API 통신 curl 연결 타임아웃입니다.
- **TIMEOUT**: 헥토파이낸셜 API 통신 curl 전체 타임아웃입니다.
- **LOG_DIR**: 로그파일을 남길 디렉터리입니다. 디렉터리가 존재해야 로그파일이 생성됩니다.
- **LOG_FILE**: 결제 또는 취소 거래에 대한 로그를 남길 파일명입니다.
- **NOTI_LOG_FILE**: 노티 처리에 대한 로그를 남길 파일명입니다.

## 📢 노티 수신 페이지

- **파일명**: receiveNoti.php
- 결제 또는 취소 완료 후 헥토파이낸셜 서버에서 콜백으로 호출하게 되는 페이지이며, 헥토파이낸셜에서 가맹점으로 노티를 전송합니다.
- 노티 수신 페이지에서는 전송받은 노티 파라미터들을 적절히 사용하여 가맹점의 실제 내부데이터, DB를 처리하시면 됩니다.
- 헥토파이낸셜에서 전송한 결제 결과(성공이든 실패든)에 상관없이 가맹점에서 성공적으로 내부데이터를 처리하셨다면 OK를, 아니라면 FAIL을 리턴하시면 됩니다.

