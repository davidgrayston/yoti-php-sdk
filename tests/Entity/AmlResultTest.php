<?php

namespace YotiTest\Service\Aml;

use Yoti\Entity\AmlResult;
use Yoti\Util\Json;
use YotiTest\TestCase;

/**
 * @coversDefaultClass \Yoti\Entity\AmlResult
 */
class AmlResultTest extends TestCase
{
    /**
     * @var \Yoti\Entity\AmlResult
     */
    public $amlResult;

    public function setup()
    {
        $this->amlResult = new AmlResult(Json::decode(file_get_contents(AML_CHECK_RESULT_JSON)));
    }

    /**
     * @covers ::isOnPepList
     * @covers ::__construct
     * @covers ::setAttributes
     * @covers ::checkAttributes
     */
    public function testIsOnPepeList()
    {
        $this->assertTrue($this->amlResult->isOnPepList());
    }

    /**
     * @covers ::isOnFraudList
     * @covers ::__construct
     * @covers ::setAttributes
     * @covers ::checkAttributes
     */
    public function testIsOnFraudList()
    {
        $this->assertFalse($this->amlResult->isOnFraudList());
    }

    /**
     * @covers ::isOnWatchList
     * @covers ::__construct
     * @covers ::setAttributes
     * @covers ::checkAttributes
     */
    public function testIsOnWatchList()
    {
        $this->assertFalse($this->amlResult->isOnWatchList());
    }
}
