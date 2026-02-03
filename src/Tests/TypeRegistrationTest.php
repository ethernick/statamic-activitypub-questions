<?php

namespace Ethernick\ActivityPubQuestions\Tests;

use Ethernick\ActivityPubCore\Services\ActivityPubTypes;
use Ethernick\ActivityPubQuestions\Http\Controllers\QuestionController;
use Tests\TestCase;

class TypeRegistrationTest extends TestCase
{
    /** @test */
    public function it_registers_question_activity_type()
    {
        $types = new ActivityPubTypes();
        $allTypes = $types->all();

        $this->assertArrayHasKey('Question', $allTypes);
        $this->assertEquals('Question', $allTypes['Question']['label']);
        $this->assertEquals(QuestionController::class, $allTypes['Question']['controller']);
        $this->assertEquals(['polls'], ActivityPubTypes::getCollections('Question'));
        $this->assertEquals(QuestionController::class, ActivityPubTypes::getController('Question'));

        $this->assertArrayHasKey('Note', $allTypes);
        $this->assertEquals('Note (aka Post)', $allTypes['Note']['label']);
        $this->assertEquals(['notes'], ActivityPubTypes::getCollections('Note'));
    }
}
