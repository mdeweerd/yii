<?php

class CFileCacheTest extends CTestCase
{
	private $cachePathModes=array(
		0777,0775,0770,0755,0750,0700
	);

	private $cacheFileModes=array(
		0666,0664,0660,0644,0640,0600
	);

	private $cachePath;

	public function setUp()
	{
		$this->cachePath=Yii::getPathOfAlias('application.runtime.CFileCacheTest');
		if(!is_dir($this->cachePath) && !(@mkdir($this->cachePath)))
			$this->markTestIncomplete('Unit tests runtime directory should have writable permissions!');

		if (substr(PHP_OS,0,3)=='WIN')
			$this->markTestSkipped("Can't reliably test it on Windows because fileperms() always return 0777.");
	}

	public function testPathMode()
	{
		foreach ($this->cachePathModes as $testMode)
		{
			$this->removeDirectory($this->cachePath);
			$app=new TestApplication(array(
				'id'=>'testApp',
				'components'=>array(
					'cache'=>array('class'=>'CFileCache','cachePath'=>$this->cachePath,'cachePathMode'=>$testMode),
				),
			));
			/** @var CFileCache $cache */
			$cache=$app->cache;

			$this->assertTrue(is_dir($cache->cachePath));
			$this->assertEquals(sprintf('%04o',$testMode),$this->getMode($cache->cachePath));
		}
	}

	public function testFileMode()
	{
		foreach ($this->cacheFileModes as $testMode)
		{
			$this->removeDirectory($this->cachePath);
			$app=new TestApplication(array(
				'id'=>'testApp',
				'components'=>array(
					'cache'=>array('class'=>'CFileCache','cachePath'=>$this->cachePath,'cacheFileMode'=>$testMode),
				),
			));
			/** @var CFileCache $cache */
			$cache=$app->cache;

			$cache->set('testKey1','testValue1');
			$files=glob($cache->cachePath.'/*.bin');
			$file=array_shift($files);

			$this->assertTrue(is_file($file));
			$this->assertEquals(sprintf('%04o',$testMode),$this->getMode($file));
		}
	}

	/**
	 * https://github.com/yiisoft/yii/issues/2435
	 */
	public function testEmbedExpiry()
	{
		$app=new TestApplication(array(
			'id'=>'testApp',
			'components'=>array(
				'cache'=>array('class'=>'CFileCache','cachePath'=>$this->cachePath,'gCProbability'=>0),
			),
		));
		$app->reset();
		$cache=$app->cache;

		$time=time();
		$cache->set('testKey1','testValue1',2);
		$files=glob($cache->cachePath.'/*.bin');
                // There can be an variation of one second
		$this->assertLessThanOrEqual($time+2,filemtime($files[0]));
		$this->assertGreaterThanOrEqual($time+1,filemtime($files[0]));

		$utime=explode(" ", microtime(false)); usleep(999999-(1000000*$utime[0]));
		$cache->set('testKey2','testValue2',2);

		$utime=explode(" ", microtime(false)); usleep(999999-(1000000*$utime[0]));
		$this->assertEquals('testValue2',$cache->get('testKey2'));

		$cache->set('testKey3','testValue3',2);
		sleep(3);
		$this->assertEquals(false,$cache->get('testKey2'));


		$app=new TestApplication(array(
			'id'=>'testApp',
			'components'=>array(
				'cache'=>array('class'=>'CFileCache','cachePath'=>$this->cachePath,'embedExpiry'=>true,'gCProbability'=>0),
			),
		));
		$app->reset();
		$cache=$app->cache;

		// Make sure that we are just after the start of a second so that the tests
		// succeed - otherwise times may be off by one second.
		$utime=explode(" ", microtime(false)); usleep(999999-(1000000*$utime[0]));

		$time=time();
		$cache->set('testKey4','testValue4',2);
		$files=glob($cache->cachePath.'/*.bin');
                // There can be an variation of one second
		$this->assertLessThanOrEqual($time,filemtime($files[0]));
		$this->assertGreaterThanOrEqual($time-1,filemtime($files[0]));

		$utime=explode(" ", microtime(false)); usleep(999999-(1000000*$utime[0]));
		$cache->set('testKey5','testValue5',2);
		$utime=explode(" ", microtime(false)); usleep(999999-(1000000*$utime[0]));
		$this->assertEquals('testValue5',$cache->get('testKey5'));

		$cache->set('testKey6','testValue6',2);
		sleep(3);
		$this->assertEquals(false,$cache->get('testKey6'));
	}

	public function tearDown()
	{
		$this->removeDirectory($this->cachePath);
	}

	private function getMode($file)
	{
		return substr(sprintf('%04o',fileperms($file)),-4);
	}

	/** @see CFileHelper::removeDirectory */
	private function removeDirectory($directory)
	{
		$items=glob($directory.DIRECTORY_SEPARATOR.'{,.}*',GLOB_MARK | GLOB_BRACE);
		foreach($items as $item)
		{
			if(basename($item)=='.' || basename($item)=='..')
				continue;
			if(substr($item,-1)==DIRECTORY_SEPARATOR)
				self::removeDirectory($item);
			else
				unlink($item);
		}
		if(is_dir($directory))
			rmdir($directory);
	}
}
