<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Services\UserSystem;

use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\PermissionData;
use App\Entity\UserSystem\PermissionsEmbed;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PermissionManagerTest extends WebTestCase
{
    protected $user_withoutGroup;

    protected $user;
    protected $group;

    /**
     * @var PermissionManager
     */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        //Get an service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(PermissionManager::class);

        //Set up a mocked user
        $user_perms = new PermissionData();
        $user_perms->setPermissionValue('parts', 'read', true) //read
            ->setPermissionValue('parts', 'edit', false) //edit
            ->setPermissionValue('parts', 'create', null) //create
            ->setPermissionValue('parts', 'move', null) //move
            ->setPermissionValue('parts', 'delete', null); //delete

        $this->user = $this->createMock(User::class);
        $this->user->method('getPermissions')->willReturn($user_perms);

        $this->user_withoutGroup = $this->createMock(User::class);
        $this->user_withoutGroup->method('getPermissions')->willReturn($user_perms);
        $this->user_withoutGroup->method('getGroup')->willReturn(null);

        //Set up a faked group
        $group1_perms = new PermissionData();
        $group1_perms
            ->setPermissionValue('parts', 'delete', false)
            ->setPermissionValue('parts', 'search', null)
            ->setPermissionValue('parts', 'read', false)
            ->setPermissionValue('parts', 'show_history', true)
            ->setPermissionValue('parts', 'edit', true);

        $this->group = $this->createMock(Group::class);
        $this->group->method('getPermissions')->willReturn($group1_perms);

        //Set this group for the user
        $this->user->method('getGroup')->willReturn($this->group);

        //parent group
        $parent_group_perms = new PermissionData();
        $parent_group_perms->setPermissionValue('parts', 'all_parts', true)
            ->setPermissionValue('parts', 'no_price_parts', false)
            ->setPermissionValue('parts', 'obsolete_parts', null);
        $parent_group = $this->createMock(Group::class);
        $parent_group->method('getPermissions')->willReturn($parent_group_perms);

        $this->group->method('getParent')->willReturn($parent_group);
    }

    public function getPermissionNames(): array
    {
        //List some permission names
        return [
            ['parts'],
            ['system'],
            ['footprints'],
            ['suppliers'],
            ['tools']
        ];
    }

    /**
     * @dataProvider getPermissionNames
     */
    public function testListOperationsForPermission($perm_name): void
    {
        $arr = $this->service->listOperationsForPermission($perm_name);

        //Every entry should not be empty.
        $this->assertNotEmpty($arr);
    }

    public function testInvalidListOperationsForPermission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        //Must throw an exception
        $this->service->listOperationsForPermission('invalid');
    }

    public function testisValidPermission(): void
    {
        $this->assertTrue($this->service->isValidPermission('parts'));
        $this->assertFalse($this->service->isValidPermission('invalid'));
    }

    public function testIsValidOperation(): void
    {
        $this->assertTrue($this->service->isValidOperation('parts', 'read'));

        //Must return false if either the permission or the operation is not existing
        $this->assertFalse($this->service->isValidOperation('parts', 'invalid'));
        $this->assertFalse($this->service->isValidOperation('invalid', 'read'));
        $this->assertFalse($this->service->isValidOperation('invalid', 'invalid'));
    }

    public function testDontInherit(): void
    {
        //Check with faked object
        $this->assertTrue($this->service->dontInherit($this->user, 'parts', 'read'));
        $this->assertFalse($this->service->dontInherit($this->user, 'parts', 'edit'));
        $this->assertNull($this->service->dontInherit($this->user, 'parts', 'create'));
        $this->assertNull($this->service->dontInherit($this->user, 'parts', 'show_history'));
        $this->assertNull($this->service->dontInherit($this->user, 'parts', 'delete'));

        //Test for user without group
        $this->assertTrue($this->service->dontInherit($this->user_withoutGroup, 'parts', 'read'));
        $this->assertFalse($this->service->dontInherit($this->user_withoutGroup, 'parts', 'edit'));
        $this->assertNull($this->service->dontInherit($this->user_withoutGroup, 'parts', 'create'));
        $this->assertNull($this->service->dontInherit($this->user_withoutGroup, 'parts', 'show_history'));
        $this->assertNull($this->service->dontInherit($this->user_withoutGroup, 'parts', 'delete'));
    }

    public function testInherit(): void
    {
        //Not inherited values should be same as dont inherit:
        $this->assertTrue($this->service->inherit($this->user, 'parts', 'read'));
        $this->assertFalse($this->service->inherit($this->user, 'parts', 'edit'));
        //When thing can not be resolved null should be returned
        $this->assertNull($this->service->inherit($this->user, 'parts', 'create'));

        //Check for inherit from group
        $this->assertTrue($this->service->inherit($this->user, 'parts', 'show_history'));
        $this->assertFalse($this->service->inherit($this->user, 'parts', 'delete'));

        //Test for user without group
        $this->assertTrue($this->service->inherit($this->user_withoutGroup, 'parts', 'read'));
        $this->assertFalse($this->service->inherit($this->user_withoutGroup, 'parts', 'edit'));
        $this->assertNull($this->service->inherit($this->user_withoutGroup, 'parts', 'create'));
        $this->assertNull($this->service->inherit($this->user_withoutGroup, 'parts', 'show_history'));
        $this->assertNull($this->service->inherit($this->user_withoutGroup, 'parts', 'delete'));
    }

    public function testSetPermission(): void
    {
        $user = new User();

        //Set permission to true
        $this->service->setPermission($user, 'parts', 'read', true);
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertTrue($this->service->inherit($user, 'parts', 'read'));

        //Set permission to false
        $this->service->setPermission($user, 'parts', 'read', false);
        $this->assertFalse($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertFalse($this->service->inherit($user, 'parts', 'read'));

        //Set permission to null
        $this->service->setPermission($user, 'parts', 'read', null);
        $this->assertNull($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertNull($this->service->inherit($user, 'parts', 'read'));
    }

    public function testSetAllPermissions(): void
    {
        $user = new User();

        //Set all permissions to true
        $this->service->setAllPermissions($user, true);
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertTrue($this->service->dontInherit($user, 'categories', 'edit'));

        //Set all permissions to false
        $this->service->setAllPermissions($user, false);
        $this->assertFalse($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertFalse($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertFalse($this->service->dontInherit($user, 'categories', 'edit'));

        //Set all permissions to null
        $this->service->setAllPermissions($user, null);
        $this->assertNull($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertNull($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertNull($this->service->dontInherit($user, 'categories', 'edit'));
    }

    public function testSetAllOperationsOfPermission(): void
    {
        $user = new User();

        //Set all operations of permission to true
        $this->service->setAllOperationsOfPermission($user, 'parts', true);
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'edit'));

        //Set all operations of permission to false
        $this->service->setAllOperationsOfPermission($user, 'parts', false);
        $this->assertFalse($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertFalse($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertFalse($this->service->dontInherit($user, 'parts', 'edit'));

        //Set all operations of permission to null
        $this->service->setAllOperationsOfPermission($user, 'parts', null);
        $this->assertNull($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertNull($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertNull($this->service->dontInherit($user, 'parts', 'edit'));
    }

    public function testEnsureCorrectSetOperations(): void
    {
        //Create an empty user (all permissions are inherit)
        $user = new User();

        //ensure that all permissions are inherit
        $this->assertNull($this->service->inherit($user, 'parts', 'read'));
        $this->assertNull($this->service->inherit($user, 'parts', 'edit'));
        $this->assertNull($this->service->inherit($user, 'categories', 'read'));

        //Set some permissions
        $this->service->setPermission($user, 'parts', 'create', true);
        //Until now only the create permission should be set
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertNull($this->service->dontInherit($user, 'parts', 'read'));

        //Now we call the ensureCorrectSetOperations method
        $this->service->ensureCorrectSetOperations($user);

        //Now all permissions should be set
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'create'));
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'read'));
        $this->assertTrue($this->service->dontInherit($user, 'parts', 'edit'));
        $this->assertTrue($this->service->dontInherit($user, 'categories', 'read'));
    }
}