$(document).ready(function() {
    $('#repeatTest').show();
    var inst=new Installer(url_check);
    $('#repeatTest').click(function(){
        inst.checkUrls().bind(inst);
    });
    inst.checkUrls();
});

//------ Response testing results constants -------
/**
 * The returned response has a successful status code and contains
 * most likely a text file (yaml/markup)
 * @type {Number}
 */
var RESPONSE_OK_TEXT=1;

/**
 * The returned response has a successful status code and contains HTML
 * @type {Number}
 */
var RESPONSE_OK_HTML=2;

/**
 * The response is 403 or 404 status code, which is expected for all
 * of the performed pre-installation tests
 * @type {Number}
 */
var RESPONSE_NA_EXPECTED=3;

/**
 * The response does not have a successful status code, but it is not expected
 * for the pre-installation tests
 * @type {Number}
 */
var RESPONSE_NA_UNEXPECTED_STATUS=4;

/**
 * No response received or other error while sending the request
 * @type {Number}
 */
var NO_RESPONSE=5;
//-----------------------------------------------------


function Installer(urls){
    this.urls=urls;
};

Installer.prototype.checkUrls = function(){
    $('#dir_listing, #config_file, #content_file')
        .text("Testing...")
        .removeClass();
    this.testRequest(this.urls.dir_listing, 'dir_listing');
    this.testRequest(this.urls.config_file, 'config_file');
    this.testRequest(this.urls.content_file, 'content_file');
};

Installer.prototype.showTestResult = function(testId, errId, message){
    var elem=$('#'+testId);
    var result;
    
    switch(errId){
        case RESPONSE_OK_TEXT:
            result='critical';
            break;
        case RESPONSE_OK_HTML:
            if(testId==='dir_listing'){
                result='critical';
            }else{
                result='warning';
            }
            break;
        case RESPONSE_NA_EXPECTED:
            result='success';
            break;
        default:
            result='warning';
            break;
    }
    
    elem.html("<strong>"+result.toUpperCase()+"</strong> - "+message);
    elem.addClass(result);
};

Installer.prototype.testRequest = function(url, testId){
    var self=this;
    $.ajax({
        url: url,
        dataType: "text",
        crossDomain: false,
        timeout: 5000,
        cache: false,
        testId: testId,
        resCallback: function(errId, message){
            self.showTestResult(this.testId, errId, message);
            
        },
        beforeSend: function( xhr ) {
            xhr.overrideMimeType( "text/plain" );
        },
        success: function( data, textStatus, xhr ){
            var isTextFile=true;
            if(data.indexOf("<html") >= 0){
                isTextFile=false;
            }
            if(isTextFile){
                this.resCallback(RESPONSE_OK_TEXT,"Text file returned, HTTP "+xhr.status+".");
            }else{
                this.resCallback(RESPONSE_OK_HTML,"Returned successful response, HTTP "+xhr.status+".");
            }
        },
        error: function( xhr, status, errorThrown ) {
            if(typeof xhr.status !== "undefined"){
                if(xhr.status===403 || xhr.status===404){
                    this.resCallback(RESPONSE_NA_EXPECTED,"Not accessible (returns HTTP "+xhr.status+").");
                }else{
                    this.resCallback(RESPONSE_NA_UNEXPECTED_STATUS,"Returned "+xhr.status+", was expecting 403 or 404.");
                }
            }else{
                this.resCallback(NO_RESPONSE,"No response received, "+errorThrown+".");
            }
        }
    });
};
