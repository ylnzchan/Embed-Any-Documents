<?php
/**
 * Dropdown Builder
 *
 * @since   1.0
 * @return  String select html
 */
function ead_selectbuilder( $name, $options,$selected="",$class="") {
    if(is_array($options)):
    echo "<select name=\"$name\" id=\"$name\" class=\"$class\">";
    foreach ($options as $key => $option) {
       echo "<option value=\"$key\"";
        if ( ! empty( $helptext ) ) {
            echo " title=\"$helptext\"";
        }
        if ( $key == $selected ) {
            echo ' selected="selected"';
        }
        echo ">$option</option>\n";
    }
    echo '</select>';
    else:

    endif;
}
/**
 * Human Readable filesize
 *
 * @since   1.0
 * @return  Human readable file size
 * @note    Replaces old gde_sanitizeOpts function
 */
function ead_human_filesize($bytes, $decimals = 2) {
    $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 * Sanitize dimensions (width, height)
 *
 * @since   1.0
 * @return  string Sanitized dimensions, or false if value is invalid
 * @note    Replaces old gde_sanitizeOpts function
 */
function ead_sanitize_dims( $dim ) {
    // remove any spacing junk
    $dim = trim( str_replace( " ", "", $dim ) );
    
    if ( ! strstr( $dim, '%' ) ) {
        $type = "px";
        $dim = preg_replace( "/[^0-9]*/", '', $dim );
    } else {
        $type = "%";
        $dim = preg_replace( "/[^0-9]*/", '', $dim );
        if ( (int) $dim > 100 ) {
            $dim = "100";
        }
    }
    
    if ( $dim ) {
        return $dim.$type;
    } else {
        return false;
    }
}
/**
 * Sanitize provider 
 *
 * @since   1.0.2
 * @return  string Sanitized provider
 */
function ead_sanitize_provider( $provider ) {
    $providerList   =   array('google','microsoft');
    if (in_array($provider, $providerList) ) {
        return $provider;
    } else {
        return 'google';
    }
} 
/**
 * Validate File url
 *
 * @since   1.0
 * @return  string Download link 
 */
function ead_validateurl($url){
    $url    =   esc_url( $url, array( 'http', 'https' ));
    $remote =   wp_remote_head($url);
    $json['status']  =  false;
    $json['message'] =  '';
    if (wp_remote_retrieve_response_code($remote) ==200) {
            //Gzip Support
            $filename = pathinfo($url);
            $doctypes   =   ead_validmimeTypes();
            if(ead_validType($url,$doctypes)){
                $json['status'] =true;
                $json['message'] = __("Done",'ead');
                if(isset($filename)){
                    $json['file']['filename'] = $filename['basename'];
                }else{
                    $json['file']['filename'] =  __("Document",'ead');
                }   
                if(isset($remote['headers']['content-length'])){
                $json['file']['filesizeHumanReadable'] = ead_human_filesize($remote['headers']['content-length']);     
                }else{
                $json['file']['filesizeHumanReadable'] =  0;
                }       
            }else{
                $json['message'] = __("File format is not supported.",'ead');
                $json['status'] = false;
            }
    }elseif(is_wp_error( $remote )){
        $json['message'] = $remote->get_error_message();  
        $json['status'] =false;
    }else{
        $json['message'] = __('Sorry, the file URL is not valid.','ead'); 
        $json['status'] =false;
    }
     return $json;
}
/**
 * Get Provider url
 *
 * @since   1.0
 * @return  string iframe embed html
 */
function ead_getprovider($atts){
    $embed  =   "";
    $durl   =   "";
    $default_width      =       ead_sanitize_dims(  get_option('ead_width','100%') );
    $default_height     =       ead_sanitize_dims(  get_option('ead_height','500px') ); 
    $default_provider   =       get_option('ead_provider','google'); 
    $default_download   =       get_option('ead_download','none'); 
    $show               =       false;
    extract(shortcode_atts( array(
            'url'       =>  '',
            'width'     =>  $default_width,
            'height'    =>  $default_height,
            'language'  =>  'en',
            'viewer'    =>  $default_provider,
            'download'  =>  $default_download
        ), $atts ) );

    if($url):
    $filedata       =     wp_remote_head( $url );
    $durl           =     '';  

    if($download=='alluser' or $download=='all'){
        $show       = true;
    }elseif($download=='logged' AND is_user_logged_in()){
        $show       = true;
    }
    if($show){
    $filesize       =   0;
    $url            = esc_url( $url, array( 'http', 'https' ));

    if (isset($filedata['headers']['content-length'])) {
        $filesize   = ead_human_filesize($filedata['headers']['content-length']);
    }else{
        $filesize   = 0;
    }    
    $fileHtml   = '';
    if($filesize)
        $fileHtml   = ' ['.$filesize.']';
        $durl       = '<p class="embed_download"><a href="'.$url.'" download >'.__('Download','ead'). $fileHtml .' </a></p>';     
    }   
    
    $url            =   esc_url( $url, array( 'http', 'https' ));
    $providerList   =   array('google','microsoft');
    if(!in_array($viewer, $providerList)) $viewer = 'google';
    $viewer         =   ead_autoviewer($url,$viewer) ;
    switch ($viewer) {
        case 'google':
            $embedsrc = '//docs.google.com/viewer?url=%1$s&embedded=true&hl=%2$s';
            $iframe = sprintf( $embedsrc, 
                urlencode( $url ),
                esc_attr( $language )
            );
            break;
        case 'microsoft':
            $embedsrc ='//view.officeapps.live.com/op/embed.aspx?src=%1$s';
            $iframe = sprintf( $embedsrc, 
                urlencode( $url )
            );
            break;
    }
    $style = 'style="width:%1$s; height:%2$s; border: none;"';
    $stylelink = sprintf($style, 
                ead_sanitize_dims($width)  ,
                ead_sanitize_dims($height) 
            );
    
    $iframe = '<iframe src="'.$iframe.'" '.$stylelink.'></iframe>';
    $show         =     false;
    $embed = $iframe.$durl;
    else:
    $embed = __('No Url Found','ead');     
    endif;
    return $embed;
}
/**
 * Select Supported Viewer
 *
 * @since   1.1
 * @return  string email html
 */
function ead_autoviewer($url,$viewer){
    $autoviewer     =   $viewer;
    if($viewer      ==  'microsoft'){
        $doctypes   =   ead_microsoft_mimes();
        if(!ead_validType($url ,$doctypes))
            $autoviewer     =  'google';
    } 
    return $autoviewer;
}
/**
 * Get Email node
 *
 * @since   1.0
 * @return  string email html
 */
function ead_getemailnode($emaildata,$postdata){
    $emailhtml = "";
    foreach ($emaildata as $key => $label) {
    if($postdata[$key]){
    $emailhtml .= '<tr bgcolor="#EAF2FA">
        <td colspan="2"><font style="font-family:sans-serif;font-size:12px"><strong>'.$label.'</strong></font></td>
        </tr>
        <tr bgcolor="#FFFFFF">
        <td width="20">&nbsp;</td>
        <td><font style="font-family:sans-serif;font-size:12px">'.$postdata[$key] .'</font></td>
        </tr>';
    }
    }
    return $emailhtml; 
}
/**
 * Validate Source mime type
 *
 * @since   1.0
 * @return  boolean 
 */
function ead_validmimeTypes(){
    include('mime_types.php');
    return $mimetypes;
}
/**
 * Checks Url Validity
 *
 * @since   1.0
 * @return  boolean 
 */
function ead_validType( $url ,$doctypes) {
    if ( is_array( $doctypes ) ) {
        $allowed_ext = implode( "|", array_keys( $doctypes ) );
        if ( preg_match( "/\.($allowed_ext)$/i", $url ) ) {
            return true;
        }
    } else {
        return false;
    }
}
/**
 * Get allowed Mime Types
 *
 * @since   1.0
 * @return  array Mimetypes 
 */
function ead_validembedtypes(){
    $doctypes=ead_validmimeTypes();
    return $allowedtype = implode(',',$doctypes); 

}
/**
 * Get allowed Mime Types for microsoft
 *
 * @since   1.0
 * @return  array Mimetypes 
 */
function ead_microsoft_mimes(){
    $micro_mime=array(
    'doc'                          => 'application/msword',
    'pot|pps|ppt'                  => 'application/vnd.ms-powerpoint',
    'xla|xls|xlt|xlw'              => 'application/vnd.ms-excel',
    'docx'                         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'dotx'                         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
    'dotm'                         => 'application/vnd.ms-word.template.macroEnabled.12',
    'xlsx'                         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xlsm'                         => 'application/vnd.ms-excel.sheet.macroEnabled.12',
    'pptx'                         => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    );
    return $micro_mime; 
}
?>