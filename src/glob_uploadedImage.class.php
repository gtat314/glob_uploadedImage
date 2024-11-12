<?php




/**
 * @global string   DIR_MEDIA
 * @global string   SUBDOMAIN_MEDIA
 * @global array    UPLOADED_IMAGE_ALLOWED_FILE_TYPES
 */
class glob_uploadedImage extends glob_dbaseTablePrimary {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $dir;

    /**
     * @var string
     */
    public $urlFolder;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $extension;

    /**
     * @var string
     */
    public $hash;

    /**
     * @var int
     */
    public $size;

    /**
     * @var string
     */
    public $sizes;

    /**
     * @var string
     */
    public $timeCreated;

    /**
     * @var int
     */
    public $moderationStatus;

    /**
     * @var string|null
     */
    public $timeEdited;

    /**
     * @var int|null
     */
    public $moderationBy;




    /**
     * @var array
     */
    private $_fh;

    /**
     * @var finfo
     */
    private $_finfo;

    /**
     * @var array
     */
    private $_pathinfo;

    /**
     * @var string
     */
    private $_newAbsFilepath;




    /**
     * @static
     * @param int $id
     * @param string|null
     * @return string|null
     */
    public static function get_url( $id, $size = null ) {

        if ( $id === null || $id === 'null' || $id === 'NULL' || $id === '' ) {

            return null;

        }

        $image = self::db_get( $id );

        if ( $image === false ) {

            return null;

        }

        $sizeToUse = '';

        if ( $size !== null ) {

            $availableSizes = unserialize( $image->sizes );

            if ( in_array( $size, $availableSizes ) ) {

                $sizeToUse = '_' . $size;

            }

        }

        return $image->urlFolder . $image->filename . $sizeToUse . '.' . $image->extension;

    }

    /**
     * @static
     * @param int $id
     * @param string|null
     * @return string|null
     */
    public static function get_filepath( $id, $size = null ) {

        if ( $id === null || $id === 'null' || $id === 'NULL' || $id === '' ) {

            return null;

        }

        $image = self::db_get( $id );

        if ( $image === false ) {

            return null;

        }

        $sizeToUse = '';

        if ( $size !== null ) {

            $availableSizes = unserialize( $image->sizes );

            if ( in_array( $size, $availableSizes ) ) {

                $sizeToUse = '_' . $size;

            }

        }

        return $image->dir . $image->filename . $sizeToUse . '.' . $image->extension;

    }

    /**
     * @static
     * @global PDO $pdo
     * @return glob_uploadedImage|false
     */
    public static function db_getSingleSafeForCompression() {

        global $pdo;

        $query = 'SELECT * FROM ' . get_called_class() . ' WHERE extension != ? AND timeCreated <= DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 0,1';

        $stmt = $pdo->prepare( $query );

        $stmt->execute([ 'webp' ]);

        $raw = $stmt->fetchAll( PDO::FETCH_CLASS, get_called_class() );

        if ( count( $raw ) === 1 ) {

            return $raw[ 0 ];

        } else {

            return false;

        }

    }

    /**
     * @return glob_uploadedImage[]
     */
    public static function db_selectAll() {

        global $pdo;

        $query = 'SELECT * FROM ' . get_called_class();

        $stmt = $pdo->prepare( $query );

        $stmt->execute();

        return $stmt->fetchAll( PDO::FETCH_CLASS, get_called_class() );

    }




    /**
     * @param int $val
     * @return int
     */
    private function _return_bytes( $val ) {

        $valNum     = (int) $val;
        $valNice    = trim( $val );
        $last       = strtolower( $val[ strlen( $valNice ) - 1 ] );
    
        switch( $last ) {
    
            case 'g':
                $valNum *= 1024;
    
            case 'm':
                $valNum *= 1024;
    
            case 'k':
                $valNum *= 1024;
    
        }
    
        return $valNum;
    
    }

    /**
     * @return int
     */
    private function _max_file_upload_in_bytes() {
    
        $max_upload     = $this->_return_bytes( ini_get( 'upload_max_filesize' ) );
        $max_post       = $this->_return_bytes( ini_get( 'post_max_size' ) );
        $memory_limit   = $this->_return_bytes( ini_get( 'memory_limit' ) );
        
        return min( $max_upload, $max_post, $memory_limit );

    }

    /**
     * @global array UPLOADED_IMAGE_ALLOWED_FILE_TYPES
     * @throws Exception
     * @return void
     */
    private function _validate() {

        if ( $this->_fh[ 'error' ] !== 0 ) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1001 );
        
        }

        if ( $this->_fh[ 'size' ] > $this->_max_file_upload_in_bytes() ) {

            throw new Exception( 'Πολύ μεγάλο μέγεθος αρχείου', 1002 );
        
        }

        if ( filesize( $this->_fh[ 'tmp_name' ] ) > $this->_max_file_upload_in_bytes() ) {

            throw new Exception( 'Πολύ μεγάλο μέγεθος αρχείου', 1003 );
        
        }

        if ( in_array( $this->_fh[ 'type' ], UPLOADED_IMAGE_ALLOWED_FILE_TYPES ) === false ) {

            throw new Exception( 'Μη έγκυρος τύπος αρχείου', 1004 );
            
        }

        $this->_finfo = new finfo( FILEINFO_MIME_TYPE );

        if ( $this->_finfo === false ) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1005 );

        }

        if ( in_array( $this->_finfo->file( $this->_fh[ 'tmp_name' ] ), UPLOADED_IMAGE_ALLOWED_FILE_TYPES ) === false ) {

            throw new Exception( 'Μη έγκυρος τύπος αρχείου', 1006 );
        
        }

        if ( $this->_finfo->file( $this->_fh[ 'tmp_name' ] ) !== $this->_fh[ 'type' ] ) {

            throw new Exception( 'Μη έγκυρος τύπος αρχείου', 2001 );
        
        }

    }

    /**
     * @throws Exception
     * @return void
     */
    private function _createData() {

        $this->_pathinfo = pathinfo( $this->_fh[ 'name' ] );

        if ( isset( $this->_pathinfo[ 'extension' ] ) === false ) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1007 );

        }

        $this->extension = $this->_pathinfo[ 'extension' ];

        $this->filename = hash( 'sha1', $this->_fh[ 'name' ] . time() );

        if ( ctype_xdigit( $this->filename ) !== true || strlen( $this->filename ) !== 40 ) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1008 );

        }

        $this->hash = hash( 'sha1', $this->_fh[ 'name' ] );

        $this->size = $this->_fh[ 'size' ];

        $this->sizes = serialize( [] );

        $this->moderationStatus = 0;

    }




    /**
     * @return glob_uploadedImage
     */
    public function __construct() {}

    /**
     * @param array $fh
     * @return glob_uploadedImage
     */
    public function initialize( $fh ) {

        $this->_fh = $fh;

        $this->_validate();

        $this->_createData();

        return $this;

    }

    /**
     * @global string DIR_MEDIA
     * @global string SUBDOMAIN_MEDIA
     * @param string $dir
     * @throws Exception
     * @return glob_uploadedImage
     */
    public function moveTo( $dir ) {

        $this->dir = DIR_MEDIA . $dir;

        $this->urlFolder = 'https://' . SUBDOMAIN_MEDIA . '/' . $dir;

        $this->_newAbsFilepath = $this->dir . $this->filename . '.' . $this->extension;

        $toRet = move_uploaded_file( $this->_fh[ 'tmp_name' ], $this->_newAbsFilepath );

        if ( $toRet === false ) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1009 );

        }

        return $this;

    }

    /**
     * @param int $widthPixels
     * @throws Exception
     * @return glob_uploadedImage
     */
    public function maxWidth( $widthPixels ) {

        $imageInfo = getimagesize( $this->_newAbsFilepath );

        if ($imageInfo === false) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1010 );

        }

        list( $originalWidth, $originalHeight, $imageType ) = $imageInfo;

        if ( $originalWidth <= intval( $widthPixels ) ) {

            return $this;

        }

        switch ( $imageType ) {
            case IMAGETYPE_JPEG:
                $originalImage = imagecreatefromjpeg( $this->_newAbsFilepath );
                break;
            case IMAGETYPE_PNG:
                $originalImage = imagecreatefrompng( $this->_newAbsFilepath );
                break;
            default:
                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1011 );
        }

        $newWidth = (int)$widthPixels;
        $newHeight = (int)( $originalHeight * ( $newWidth / $originalWidth ) );

        $newImage = imagecreatetruecolor( $newWidth, $newHeight );

        if ( $newImage === false ) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1012 );

        }

        $return = imagecopyresampled( $newImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight );

        if ( $return === false ) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1013 );

        }

        switch ( $imageType ) {
            case IMAGETYPE_JPEG:
                imagejpeg( $newImage, $this->_newAbsFilepath );
                break;
            case IMAGETYPE_PNG:
                imagepng( $newImage, $this->_newAbsFilepath );
                break;
        }

        imagedestroy( $newImage );
        imagedestroy( $originalImage );

        return $this;

    }

    /**
     * @param array $sizes
     * @throws Exception
     * @return glob_uploadedImage
     */
    public function sizesRectangle( Array $sizes ) {

        $this->sizes = serialize( $sizes );

        foreach ( $sizes as $sizeRaw ) {

            $sizeInt = (int) $sizeRaw;

            $image = imagecreatefromstring( file_get_contents( $this->_newAbsFilepath ) );

            if ( $image === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1010 );
    
            }

            $width = imagesx( $image );

            if ( $image === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1011 );
    
            }

            $height = imagesy( $image );

            if ( $image === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1012 );
    
            }

            $size = min( $width, $height );

            if ( $size < $sizeInt ) {

                continue;

            }

            $x = intval( ( $width  - $size ) / 2 );
            $y = intval( ( $height - $size ) / 2 );

            $square = imagecreatetruecolor( $size, $size );

            if ( $square === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1013 );
    
            }

            $result = imagecopy( $square, $image, 0, 0, $x, $y, $size, $size );

            if ( $result === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1014 );
    
            }

            $resized = imagecreatetruecolor( $sizeInt, $sizeInt );

            if ( $resized === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1015 );
    
            }

            $result = imagecopyresampled($resized, $square, 0, 0, 0, 0, $sizeInt, $sizeInt, $size, $size);

            if ( $result === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1016 );
    
            }

            $newFilePath = $this->dir . $this->filename . '_' . $sizeRaw . '.' . $this->extension;

            $result = imagejpeg( $resized, $newFilePath );

            if ( $result === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1017 );
    
            }

            imagedestroy($image);
            imagedestroy($square);
            imagedestroy($resized);

        }

        return $this;

    }

    /**
     * @param array $sizes
     * @throws Exception
     * @return glob_uploadedImage
     */
    public function sizes( Array $sizes ) {

        $this->sizes = serialize( $sizes );

        $imageInfo = getimagesize( $this->_newAbsFilepath );

        if ($imageInfo === false) {

            throw new Exception( 'Παρουσιάστηκε σφάλμα', 1010 );

        }

        list( $originalWidth, $originalHeight, $imageType ) = $imageInfo;

        switch ( $imageType ) {
            case IMAGETYPE_JPEG:
                $originalImage = imagecreatefromjpeg( $this->_newAbsFilepath );
                break;
            case IMAGETYPE_PNG:
                $originalImage = imagecreatefrompng( $this->_newAbsFilepath );
                break;
            default:
                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1011 );
        }

        foreach ( $sizes as $size ) {

            $newWidth = (int)$size;
            $newHeight = (int)( $originalHeight * ( $newWidth / $originalWidth ) );

            $newImage = imagecreatetruecolor( $newWidth, $newHeight );

            if ( $newImage === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1012 );
    
            }

            $return = imagecopyresampled( $newImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight );

            if ( $return === false ) {

                throw new Exception( 'Παρουσιάστηκε σφάλμα', 1013 );
    
            }

            $outputImagePath = $this->dir . $this->filename . '_' . $newWidth . '.' . $this->extension;

            switch ( $imageType ) {
                case IMAGETYPE_JPEG:
                    imagejpeg( $newImage, $outputImagePath );
                    break;
                case IMAGETYPE_PNG:
                    imagepng( $newImage, $outputImagePath );
                    break;
            }

            imagedestroy( $newImage );

        }

        imagedestroy( $originalImage );

        return $this;

    }

    /**
     * @param array $sizes
     * @throws Exception
     * @return glob_uploadedImage|false
     */
    public function rescale( Array $sizes ) {

        if ( serialize( $sizes ) === $this->sizes ) {

            return false;

        }

        $this->_newAbsFilepath = $this->dir . $this->filename . '.' . $this->extension;

        if ( file_exists( $this->_newAbsFilepath ) === false ) {

            return false;

        }

        $oldSizes = unserialize( $this->sizes );

        for ( $i = 0 ; $i < count( $oldSizes ) ; $i++ ) {

            unlink( $this->dir . $this->filename . '_' . $oldSizes[ $i ] . '.' . $this->extension );

        }

        $this->sizes( $sizes );

        return $this;

    }

    /**
     * @param int $maxWidthPixels
     * @throws Exception
     * @return glob_uploadedImage|false
     */
    public function rescaleMainImage( $maxWidthPixels ) {

        $this->_newAbsFilepath = $this->dir . $this->filename . '.' . $this->extension;

        if ( file_exists( $this->_newAbsFilepath ) === false ) {

            return false;

        }

        $this->maxWidth( $maxWidthPixels );

        return $this;

    }

    /**
     * @return array
     */
    public function get_sizesList() {

        return unserialize( $this->sizes );

    }

    /**
     * @return string|null
     */
    public function get_basename( $size = null ) {

        if ( $size === null ) {

            return $this->filename . '.' . $this->extension;

        } else {

            return $this->filename . '_' . $size . '.' . $this->extension;

        }

    }

    /**
     * @param string|null $size
     * @return string
     */
    public function get_fullUrl( $size = null ) {

        if ( $size === null ) {

            return $this->urlFolder . $this->filename . '.' . $this->extension;

        } else {

            return $this->urlFolder . $this->filename . '_' . $size . '.' . $this->extension;

        }

    }

    /**
     * @return array
     */
    public function get_allSizes() {

        $sizesToReturn = [
            'default' => $this->filename . '.' . $this->extension
        ];

        $availableSizes = unserialize( $this->sizes );

        foreach ( $availableSizes as $availableSize ) {

            $sizesToReturn[ $availableSize ] = $this->filename . '_' . $availableSize . '.' . $this->extension;

        }

        return $sizesToReturn;

    }

    /**
     * @return array
     */
    public function get_allFilePaths() {

        $sizesToReturn = [
            'default' => $this->dir . $this->filename . '.' . $this->extension
        ];

        $availableSizes = unserialize( $this->sizes );

        foreach ( $availableSizes as $availableSize ) {

            $sizesToReturn[ $availableSize ] = $this->dir . $this->filename . '_' . $availableSize . '.' . $this->extension;

        }

        return $sizesToReturn;

    }

    /**
     * @return void
     */
    public function deleteFiles() {

        if ( file_exists( $this->dir . $this->filename . '.' . $this->extension ) ) {

            unlink( $this->dir . $this->filename . '.' . $this->extension );

        }

        $availableSizes = unserialize( $this->sizes );

        foreach ( $availableSizes as $availableSize ) {

            if ( file_exists( $this->dir . $this->filename . '_' . $availableSize . '.' . $this->extension ) ) {

                unlink( $this->dir . $this->filename . '_' . $availableSize . '.' . $this->extension );

            }

        }

    }

    /**
     * @return void
     */
    public function delete() {

        $this->deleteFiles();

        $this->db_delete();

    }

}