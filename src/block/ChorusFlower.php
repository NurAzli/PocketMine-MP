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

namespace pocketmine\block;

use pocketmine\block\utils\AgeableTrait;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\block\StructureGrowEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\RayTraceResult;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\ChorusFlowerDieSound;
use pocketmine\world\sound\ChorusFlowerGrowSound;
use function array_rand;
use function min;
use function mt_rand;

final class ChorusFlower extends Flowable{
	use AgeableTrait;
	use StaticSupportTrait;

	public const MIN_AGE = 0;
	public const MAX_AGE = 5;

	private const MAX_STEM_HEIGHT = 5;

	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()];
	}

	private function canBeSupportedAt(Block $block) : bool{
		$down = $block->getSide(Facing::DOWN);

		if($down->getTypeId() === BlockTypeIds::END_STONE || $down->getTypeId() === BlockTypeIds::CHORUS_PLANT){
			return true;
		}

		$plantAdjacent = false;
		foreach(Facing::HORIZONTAL as $side){
			$sideBlock = $block->getSide($side);

			if($sideBlock->getTypeId() === BlockTypeIds::CHORUS_PLANT){
				if($plantAdjacent){ //at most one plant may be horizontally adjacent
					return false;
				}
				$plantAdjacent = true;
			}elseif($sideBlock->getTypeId() !== BlockTypeIds::AIR){
				return false;
			}
		}

		return $plantAdjacent;
	}

	public function onProjectileHit(Projectile $projectile, RayTraceResult $hitResult) : void{
		$this->position->getWorld()->useBreakOn($this->position);
	}

	/**
	 * @phpstan-return array{int, bool}
	 */
	private function scanStem() : array{
		$world = $this->position->getWorld();

		$stemHeight = 0;
		$endStoneBelow = false;
		for($yOffset = 0; $yOffset < self::MAX_STEM_HEIGHT; $yOffset++, $stemHeight++){
			$down = $this->getSide(Facing::DOWN, $yOffset + 1);

			if($down->getTypeId() !== BlockTypeIds::CHORUS_PLANT){
				if($down->getTypeId() === BlockTypeIds::END_STONE){
					$endStoneBelow = true;
				}
				break;
			}
		}

		return [$stemHeight, $endStoneBelow];
	}

	private function allHorizontalBlocksEmpty(BlockPosition $position, ?int $except) : bool{
		$world = $position->getWorld();
		foreach(Facing::HORIZONTAL as $facing){
			if($facing === $except){
				continue;
			}
			if($world->getBlock($position->getSide($facing))->getTypeId() !== BlockTypeIds::AIR){
				return false;
			}
		}

		return true;
	}

	private function canGrowUpwards(int $stemHeight, bool $endStoneBelow) : bool{
		$world = $this->position->getWorld();

		$up = $this->position->getSide(Facing::UP);
		if(
			//the space above must be empty and writable
			!$world->isInWorld($up->x, $up->y, $up->z) ||
			$world->getBlock($up)->getTypeId() !== BlockTypeIds::AIR ||
			(
				//the space above that must be empty, but doesn't need to be writable
				$world->isInWorld($up->x, $up->y + 1, $up->z) &&
				$world->getBlock($up->getSide(Facing::UP))->getTypeId() !== BlockTypeIds::AIR
			)
		){
			return false;
		}

		if($this->getSide(Facing::DOWN)->getTypeId() !== BlockTypeIds::AIR){
			if($stemHeight >= self::MAX_STEM_HEIGHT){
				return false;
			}

			if($stemHeight > 1 && $stemHeight > mt_rand(0, $endStoneBelow ? 4 : 3)){ //chance decreases for each added block of chorus plant
				return false;
			}
		}

		return $this->allHorizontalBlocksEmpty($up, null);
	}

	private function grow(int $facing, int $ageChange, ?BlockTransaction $tx) : BlockTransaction{
		if($tx === null){
			$tx = new BlockTransaction($this->position->getWorld());
		}
		$tx->addBlock($this->position->getSide($facing), (clone $this)->setAge(min(self::MAX_AGE, $this->age + $ageChange)));

		return $tx;
	}

	public function ticksRandomly() : bool{ return $this->age < self::MAX_AGE; }

	public function onRandomTick() : void{
		$world = $this->position->getWorld();

		if($this->age >= self::MAX_AGE){
			return;
		}

		$tx = null;

		[$stemHeight, $endStoneBelow] = $this->scanStem();
		if($this->canGrowUpwards($stemHeight, $endStoneBelow)){
			$tx = $this->grow(Facing::UP, 0, $tx);
		}else{
			$facingVisited = [];
			for($attempts = 0, $maxAttempts = mt_rand(0, $endStoneBelow ? 4 : 3); $attempts < $maxAttempts; $attempts++){
				$facing = Facing::HORIZONTAL[array_rand(Facing::HORIZONTAL)];
				if(isset($facingVisited[$facing])){
					continue;
				}
				$facingVisited[$facing] = true;

				$sidePosition = $this->position->getSide($facing);
				if(
					$world->getBlock($sidePosition)->getTypeId() === BlockTypeIds::AIR &&
					$world->getBlock($sidePosition->getSide(Facing::DOWN))->getTypeId() === BlockTypeIds::AIR &&
					$this->allHorizontalBlocksEmpty($sidePosition, Facing::opposite($facing))
				){
					$tx = $this->grow($facing, 1, $tx);
				}
			}
		}

		if($tx !== null){
			$tx->addBlock($this->position, VanillaBlocks::CHORUS_PLANT());
			$ev = new StructureGrowEvent($this, $tx, null);
			$ev->call();
			if(!$ev->isCancelled() && $tx->apply()){
				$world->addSound($this->position->center(), new ChorusFlowerGrowSound());
			}
		}else{
			$world->addSound($this->position->center(), new ChorusFlowerDieSound());
			$this->position->getWorld()->setBlock($this->position, $this->setAge(self::MAX_AGE));
		}
	}
}
