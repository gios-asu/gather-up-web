<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use GatherUp\Encryption\RsaEncryption;

class SignUpControllerTest extends TestCase
{
    use DatabaseTransactions;

    private $user;
    private $authToken;
    private $team;
    private $teamKey;
    private $event;
    private $anotherUsersEvent;
    private $signUpMessage;

    public function setUp()
    {
        parent::setUp();

        $this->signUpMessage = '{
            "first_name": "Ivan",
            "last_name": "M",
            "email": "test@test.com",
            "birth_date": "2015-11-25"
        }';
    }

    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    |
    | These functions are called by transaction wrapped tests
    | that will insert data into the database when needed
    |
    */

    public function insertRecords()
    {
        $this->user = factory(GatherUp\Models\User::class)->create([
            'name' => str_random(10),
            'email' => str_random(10).'@gmail.com',
            'password' => 'secret',
        ]);
        $this->team = factory(GatherUp\Models\Team::class)->create([
            'name' => str_random(10),
            'owner_id' => $this->user->id,
        ]);
        $this->teamKey = factory(GatherUp\Models\TeamKey::class)->create([
            'team_id' => $this->team->id,
        ]);
        $this->authToken = factory(GatherUp\Models\AuthToken::class)->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'token' => 'test',
        ]);
        $this->event = factory(GatherUp\Models\Event::class)->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);
    }

    public function insertAnotherUsersEvent()
    {
        $user = factory(GatherUp\Models\User::class)->create([
            'name' => str_random(10),
            'email' => str_random(10).'@gmail.com',
            'password' => 'secret',
        ]);
        $team = factory(GatherUp\Models\Team::class)->create([
            'name' => str_random(10),
            'owner_id' => $user->id,
        ]);
        $teamKey = factory(GatherUp\Models\TeamKey::class)->create([
            'team_id' => $team->id,
        ]);
        $authToken = factory(GatherUp\Models\AuthToken::class)->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'token' => 'different token',
        ]);
        $this->anotherUsersEvent = factory(GatherUp\Models\Event::class)->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Actual Tests
    |--------------------------------------------------------------------------
    |
    | Tests may insert records
    |
    */

    public function testCanStoreSignUp()
    {
        $this->insertRecords();

        $rsa = new RsaEncryption();
        $publicKey = $this->teamKey->public_key;

        $cipherSignUp = $rsa->encryptMessage($this->signUpMessage, $publicKey);

        $response = $this->post('/api/v1/sign_up', [
            'token' => 'test',
            'event_id' => $this->event->id,
            'cipher_sign_up' => $cipherSignUp,
        ])->seeJson([
            'success' => true
        ])->seeStatusCode(200);
    }

    /**
     * Test that the call fails because of a malformed cipher payload
     */
    public function testCannotStoreSignUpBecauseOfBadRequest()
    {
        $this->insertRecords();

        $response = $this->post('/api/v1/sign_up', [
            'token' => 'test',
            'event_id' => $this->event->id,
            'cipher_sign_up' => 'bad text',
        ])->seeJson([
            'success' => false
        ])->seeStatusCode(400);
    }

    /**
     * Test that the call fails because the user is unauthorized to
     * add a sign up to an event that their given token is not for
     */
    public function testCannotStoreSignUpBecauseUnauthorizedEvent()
    {
        $this->insertRecords();
        $this->insertAnotherUsersEvent();

        $rsa = new RsaEncryption();
        $publicKey = $this->teamKey->public_key;

        $cipherSignUp = $rsa->encryptMessage($this->signUpMessage, $publicKey);

        $response = $this->post('/api/v1/sign_up', [
            'token' => 'test',
            'event_id' => $this->anotherUsersEvent->id,
            'cipher_sign_up' => $cipherSignUp,
        ])->seeJson([
            'authorization' => 'This action is unauthorized.'
        ])->seeStatusCode(403);
    }
}
