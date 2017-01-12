<?php
use PHPUnit\Framework\TestCase;

include_once('./core/class/cookie.class.php');
include_once('./core/class/mediaSource.class.php');
include_once('./core/class/hipservNasService.class.php');
 
/**
* Test case for hipservNasService
*/
class hipservNasServiceTest extends TestCase
{
    private function getXmlForAuthentificationToken($code, $userName, $authCode, $hipservUrl)
    {
        $xml = '<sessionhandler code="' . $code . '" description="error">' .
                    '<session hipserv="HIPSERV" username="' . $userName .
                    '" auth="' . $authCode . '" url="' . $hipservUrl . '" />' .
                '</sessionhandler>';
        
        return $xml;
    }

    private function getXmlForUserSession($userUri)
    {
        $xml = '<session userURI="' . $userUri . '" />';

        return $xml;
    }

    private function getXmlForUserInformation()
    {
        $xml = '<user href="/api/2.0/rest/accounts/users/johndoe" username="johndoe"' .
                ' displayName="John Doe" isAdmin="true" isGuest="false" isHipServUser="true"' .
                ' isFamilyMember="true" albumsURI="/api/2.0/rest/albums/users/am9obmRvZQ"' .
                ' contactsURI="/api/2.0/rest/contacts/johndoe" themesURI="/api/2.0/rest/themes">' .
                '<mediaSources>' .
                '<mediaSource id="FamilyLibrary" name="FamilyLibrary" href="/api/2.0/rest/files/ZmFtaWx5"' .
                ' backupHref="/api/2.0/rest/backup/files/ZmFtaWx5"/>' .
                '<mediaSource id="MyLibrary" name="MyLibrary" href="/api/2.0/rest/files/users/johndoe/TXlMaWJyYXJ5"' .
                ' backupHref="/api/2.0/rest/backup/files/users/johndoe/TXlMaWJyYXJ5"/>' .
                '<mediaSource id="MyComputers" name="MyComputers" href="/api/2.0/rest/files/users/johndoe/TXlDb21wdXRlcnM"' .
                ' backupHref="/api/2.0/rest/backup/files/users/johndoe/TXlDb21wdXRlcnM"/>' .
                '</mediaSources>' .
                '</user>';

        return $xml;
    }

    private function getXmlForDirectoryContent()
    {
        $xml = '<file name="Samples" href="/api/2.0/rest/files/ZmFtaWx5L1NhbXBsZXM" type="folder">' . 
                '<file name="Sample Folder" type="folder"' .
                ' href="/api/2.0/rest/files/ZmFtaWx5L1NhbXBsZXMvU2FtcGxlIEZvbGRlcg"' .
                ' sharingWithDMA="true" nbFolders="0" nbFiles="0" mtime="1285158852"/>' .
                '<file name="06 - Dissolved Girl.mp3" type="audio"' .
                ' href="/api/2.0/rest/files/ZmFtaWx5L1NhbXBsZXMvMDYgLSBEaXNzb2x2ZWQgR2lybC5tcDM"' .
                ' mimeType="audio/mpeg" size="5873792" mtime="1285158715"/>' .
               '</file>';

        return $xml;
    }
    
    public function testGetAuthentificationTokenWhenReturnCodeNotZeroShouldReturnErrorString()
    {
        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getXml'])
        ->getMock();
        $target->cloudServerAddress = 'http://mycloud';
        $target->method('getXml')
        ->will($this->onConsecutiveCalls(
            $this->getXmlForAuthentificationToken('1', '', '', '')
        ));
        
        $result = $target->getAuthentificationToken('', '', '');
        $this->assertNotEmpty($result);
    }

    public function testGetAuthentificationTokenWhenAuthIsOkShouldGetTheToken()
    {
        $authToken = '1234';
        $hipservUrl = 'http://127.0.0.1/';
        $xml = $this->getXmlForAuthentificationToken('0', 'user', $authToken, $hipservUrl);

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getXml'])
        ->getMock();
        $target->cloudServerAddress = 'http://mycloud';
        $target->method('getXml')
                ->willReturn($xml);
        
        $result = $target->getAuthentificationToken('nas', 'uesr', 'password');
        $this->assertEmpty($result);
        $this->assertEquals($target->authToken, $authToken);
        $this->assertEquals($target->nasUrl, $hipservUrl);
    }

    public function testLogonWhenLogonShouldGetTheUserUri()
    {
        $userUri = 'userUri';
        $xml = $this->getXmlForUserSession($userUri);

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getXml'])
        ->getMock();
        $target->cloudServerAddress = 'http://mycloud';
        $target->method('getXml')
                ->willReturn($xml);
        
        $target->logon();
        $this->assertEquals($target->userURI, $userUri);
    }

    public function testShutdown()
    {
        $nasUrl = '/api/2.0/rest/server/config/admin';
        $xml = '<?xml version="1.0"?><command name="shutdown"/>';
        $method = 'PUT';

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getXml'])
        ->getMock();
        $target->cloudServerAddress = 'http://mycloud';
        $target->expects($this->once())
                ->method('getXml')
                ->with($this->equalTo($nasUrl), $this->equalTo($xml), $this->equalTo($method));

        $target->shutDown();
    }

    public function testRestart()
    {
        $nasUrl = '/api/2.0/rest/server/config/admin';
        $xml = '<?xml version="1.0"?><command name="restart"/>';
        $method = 'PUT';

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getXml'])
        ->getMock();
        $target->cloudServerAddress = 'http://mycloud';
        $target->expects($this->once())
                ->method('getXml')
                ->with($this->equalTo($nasUrl), $this->equalTo($xml), $this->equalTo($method));

        $target->restart();
    }

    public function testGetUserInformation()
    {
        $xml = $this->getXmlForUserInformation();

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getXml'])
        ->getMock();
        $target->cloudServerAddress = 'http://mycloud';
        $target->method('getXml')
        ->willReturn($xml);

        $target->getUserInformation();
        
        $this->assertEquals(sizeof($target->rootMediaSources), 3);
        $this->assertEquals($target->rootMediaSources[0]->id, 'FamilyLibrary');
        $this->assertEquals($target->rootMediaSources[0]->type, 'folder');
        $this->assertEquals($target->rootMediaSources[1]->id, 'MyLibrary');
        $this->assertEquals($target->rootMediaSources[2]->id, 'MyComputers');
    }

    public function testGetDirectoryContent()
    {
        $xml = $this->getXmlForDirectoryContent();

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getXml'])
        ->getMock();
        $target->cloudServerAddress = 'http://mycloud';
        $target->method('getXml')
        ->willReturn($xml);

        $result = $target->getDirectoryContent('');
        
        $this->assertEquals(2, sizeof($result));
        $this->assertEquals($result[0]->name, 'Sample Folder');
        $this->assertEquals($result[0]->type, 'folder');
    }

    public function testGetHrefOfPathWhenPathExistsReturnTheHref()
    {
        $xml = $this->getXmlForDirectoryContent();

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getDirectoryContent'])
        ->getMock();

        $root = new mediaSource();
        $root->name = 'FamilyLibrary';
        $root->type = 'folder';

        $folder1 = new mediaSource();
        $folder1->name = 'Folder1';
        $folder1->type = 'folder';
        $file = new mediaSource();
        $file->type = "video";

        $folder2 = new mediaSource();
        $folder2->name = 'Folder2';
        $folder2->type = 'folder';

        $source1 = [0 => $folder1, 1 => $file];
        $source2 = [0 => $folder2, 1 => $file];

        $target->cloudServerAddress = 'http://mycloud';
        array_push($target->rootMediaSources, $root);

        $target->method('getDirectoryContent')
        ->will($this->onConsecutiveCalls($source1, $source2));

        $result = $target->getDirectoryOfPath('/FamilyLibrary/Folder1/Folder2');
        
        $this->assertNotNull($result);
        $this->assertEquals($result->name, $folder2->name);
    }

    public function testGetHrefOfPathWhenPathNotExistsReturnNull()
    {
        $xml = $this->getXmlForDirectoryContent();

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getDirectoryContent'])
        ->getMock();

        $root = new mediaSource();
        $root->name = 'FamilyLibrary';
        $root->type = 'folder';

        $folder1 = new mediaSource();
        $folder1->name = 'Folder1';
        $folder1->type = 'folder';
        $file = new mediaSource();
        $file->type = "video";

        $folder2 = new mediaSource();
        $folder2->name = 'Folder2';
        $folder2->type = 'folder';

        $source1 = [0 => $folder1, 1 => $file];
        $source2 = [0 => $folder2, 1 => $file];

        $target->cloudServerAddress = 'http://mycloud';
        array_push($target->rootMediaSources, $root);

        $target->method('getDirectoryContent')
        ->will($this->onConsecutiveCalls($source1, $source2));

        $result = $target->getDirectoryOfPath('/FamilyLibrary/Folder1/Folder3');
        
        $this->assertNull($result);
    }

    public function testGetHrefOfPathWhenPathStringEmptyShouldReturnNull()
    {
        $xml = $this->getXmlForDirectoryContent();

        $target = $this->getMockBuilder(hipservNasService::class)
        ->setMethods(['logDebug', 'getDirectoryContent'])
        ->getMock();

        $root = new mediaSource();
        $root->name = 'FamilyLibrary';
        $root->type = 'folder';

        $folder1 = new mediaSource();
        $folder1->name = 'Folder1';
        $folder1->type = 'folder';
        $file = new mediaSource();
        $file->type = "video";

        $folder2 = new mediaSource();
        $folder2->name = 'Folder2';
        $folder2->type = 'folder';

        $source1 = [0 => $folder1, 1 => $file];
        $source2 = [0 => $folder2, 1 => $file];

        $target->cloudServerAddress = 'http://mycloud';
        array_push($target->rootMediaSources, $root);

        $target->method('getDirectoryContent')
        ->will($this->onConsecutiveCalls($source1, $source2));

        $result = $target->getDirectoryOfPath('');
        
        $this->assertNull($result);
    }
}
