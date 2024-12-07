<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use function array_filter;

final class CombinedInventoryTest extends \PHPUnit\Framework\TestCase{

	/**
	 * @return Inventory[]
	 * @phpstan-return list<Inventory>
	 */
	private function createInventories() : array{
		$inventory1 = new SimpleInventory(1);
		$inventory1->setItem(0, VanillaItems::APPLE());
		$inventory2 = new SimpleInventory(1);
		$inventory2->setItem(0, VanillaItems::PAPER());
		$inventory3 = new SimpleInventory(2);
		$inventory3->setItem(1, VanillaItems::BONE());

		return [$inventory1, $inventory2, $inventory3];
	}

	/**
	 * @param Item[] $items
	 * @phpstan-param array<int, Item> $items
	 */
	private function verifyReadItems(array $items) : void{
		self::assertSame(ItemTypeIds::APPLE, $items[0]->getTypeId());
		self::assertSame(ItemTypeIds::PAPER, $items[1]->getTypeId());
		self::assertTrue($items[2]->isNull());
		self::assertSame(ItemTypeIds::BONE, $items[3]->getTypeId());
	}

	/**
	 * @return Item[]
	 * @phpstan-return list<Item>
	 */
	private static function getAltItems() : array{
		return [
			VanillaItems::AMETHYST_SHARD(),
			VanillaItems::AIR(), //null item
			VanillaItems::BLAZE_POWDER(),
			VanillaItems::BRICK()
		];
	}

	public function testGetItem() : void{
		$inventory = new CombinedInventory($this->createInventories());

		$this->verifyReadItems([
			$inventory->getItem(0),
			$inventory->getItem(1),
			$inventory->getItem(2),
			$inventory->getItem(3)
		]);

		$this->expectException(\InvalidArgumentException::class);
		$inventory->getItem(4);
	}

	public function testGetContents() : void{
		$inventory = new CombinedInventory($this->createInventories());

		$this->verifyReadItems($inventory->getContents(includeEmpty: true));

		$contentsWithoutEmpty = $inventory->getContents(includeEmpty: false);
		self::assertFalse(isset($contentsWithoutEmpty[2]), "This index should not be set during this test");
		self::assertCount(3, $contentsWithoutEmpty);
		$this->verifyReadItems([
			$contentsWithoutEmpty[0],
			$contentsWithoutEmpty[1],
			VanillaItems::AIR(),
			$contentsWithoutEmpty[3]
		]);
	}

	/**
	 * @param Inventory[] $backing
	 * @param Item[]      $altItems
	 *
	 * @phpstan-param array<int, Inventory> $backing
	 * @phpstan-param array<int, Item> $altItems
	 */
	private function verifyWriteItems(array $backing, array $altItems) : void{
		foreach([
			0 => [$backing[0], 0],
			1 => [$backing[1], 0],
			2 => [$backing[2], 0],
			3 => [$backing[2], 1]
		] as $combinedSlot => [$backingInventory, $backingSlot]){
			if(!isset($altItems[$combinedSlot])){
				self::assertTrue($backingInventory->isSlotEmpty($backingSlot));
			}else{
				self::assertSame($altItems[$combinedSlot]->getTypeId(), $backingInventory->getItem($backingSlot)->getTypeId());
			}
		}
	}

	public function testSetItem() : void{
		$backing = $this->createInventories();
		$inventory = new CombinedInventory($backing);

		$altItems = self::getAltItems();
		foreach($altItems as $slot => $item){
			$inventory->setItem($slot, $item);
		}
		$this->verifyWriteItems($backing, $altItems);

		$this->expectException(\InvalidArgumentException::class);
		$inventory->setItem(4, VanillaItems::BRICK());
	}

	/**
	 * @phpstan-return \Generator<int, array{array<int, Item>}, void, void>
	 */
	public static function setContentsProvider() : \Generator{
		$altItems = self::getAltItems();

		yield [$altItems];
		yield [array_filter($altItems, fn(Item $item) => !$item->isNull())];
	}

	/**
	 * @dataProvider setContentsProvider
	 * @param Item[] $altItems
	 * @phpstan-param array<int, Item> $altItems
	 */
	public function testSetContents(array $altItems) : void{
		$backing = $this->createInventories();
		$inventory = new CombinedInventory($backing);
		$inventory->setContents($altItems);

		$this->verifyWriteItems($backing, $altItems);
	}

	public function testGetSize() : void{
		self::assertSame(4, (new CombinedInventory($this->createInventories()))->getSize());
	}

	public function testGetMatchingItemCount() : void{
		$inventory = new CombinedInventory($this->createInventories());
		//we don't need to test the base functionality, only ensure that the correct delegate is called
		self::assertSame(1, $inventory->getMatchingItemCount(3, VanillaItems::BONE(), true));
		self::assertNotSame(1, $inventory->getMatchingItemCount(3, VanillaItems::PAPER(), true));
	}

	public function testIsSlotEmpty() : void{
		$inventory = new CombinedInventory($this->createInventories());

		self::assertTrue($inventory->isSlotEmpty(2));
		self::assertFalse($inventory->isSlotEmpty(0));
		self::assertFalse($inventory->isSlotEmpty(1));
		self::assertFalse($inventory->isSlotEmpty(3));
	}
}
