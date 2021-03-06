<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Facade\FlareClient\Http\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function itListsAllOfficesInPaginatedWay()
    {
        Office::factory(30)->create();

        $response = $this->get('/api/offices');
        $response->dump();
        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(20, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'title']]]);
    }


    /**
     * A basic feature test example.
     *
     * @return void
     */

    public function itOnlyListsOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();

        Office::factory()->hidden()->create();
        Office::factory()->pending()->create();

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function itFiltersByUserId()
    {
        Office::factory(3)->create();
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $response = $this->get(
            '/api/offices?user_id=' . $host->id
        );
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();
        $user = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(
            '/api/offices?visitor_id=' . $user->id
        );
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }


    public function itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office =  Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        dd(
            $office->image()
        );

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonCount(1, 'data.0.tags')
            ->assertJsonCount(1, 'data.0.images')
            ->assertJsonPath('data.0.user.id', $user->id);
    }

    /**
     * @test
     */
    public function itReturnsTheNumberOfActiveReservations()
    {
        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCALLED]);

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonPath('data.0.reservations_count', 1);
    }

    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        Office::factory()->create([
            'lat' => '39.74051727562952',
            'lng' => '-8.770375324893696',
            'title' => 'Leiria'
        ]);

        Office::factory()->create([
            'lat' => '39.07753883078113',
            'lng' => '-9.281266331143293',
            'title' => 'Torres Vedras'
        ]);

        $response = $this->get('/api/offices?lat=38.720661384644046&lng=-9.16044783453807');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Torres Vedras')
            ->assertJsonPath('data.1.title', 'Leiria');

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Leiria')
            ->assertJsonPath('data.1.title', 'Torres Vedras');
    }

    /**
     * @test
     */
    public function itCreatesAnOffice()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/offices', Office::factory()->raw([
            'tags' => $tags->pluck('id')->toArray()
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.reservations_count', 0)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseHas('offices', [
            'id' => $response->json('data.id')
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
     * @test
     */
    public function itDoesntAllowCreatingIfScopeIsNotProvided()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, []);

        $response = $this->postJson('/offices');

        $response->assertForbidden();
    }
    public function itShowsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office =  Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);


        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCALLED]);


        $response = $this->get('/api/offices/' . $office->id);

        $this->assertEquals(1, $response->json('data')['resrvation_count']);
        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }


    public function itUpdatesAnOffice()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(3)->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $anotherTag = Tag::factory()->create();

        $response = $this->putJson('/offices/' . $office->id, [
            'title' => 'Amazing Office',
            'tags' => [$tags[0]->id, $anotherTag->id]
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $anotherTag->id)
            ->assertJsonPath('data.title', 'Amazing Office');
    }

    public function itDoesntUpdateOfficeThatDoesntBelongToUser()
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $office = Office::factory()->for($anotherUser)->create();

        $this->actingAs($user);

        $response = $this->putJson('/offices/' . $office->id, [
            'title' => 'Amazing Office'
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function itMarksTheOfficeAsPendingIfDirty()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/offices/' . $office->id, [
            'lat' => 40.74051727562952
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING,
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }
}
