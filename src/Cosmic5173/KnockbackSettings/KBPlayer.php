<?php

/*
 *   KnockbackSettings - Edit server knockback settings.
 *   Copyright (C) 2022  Armen Deroian
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Cosmic5173\KnockbackSettings;

use pocketmine\math\Vector3;
use pocketmine\player\Player;

class KBPlayer extends Player {

    /**
     * @return float
     */
    public function getGravity(): float {
        return $this->gravity;
    }

    /**
     * @param float $gravity
     */
    public function setGravity(float $gravity): void {
        $this->gravity = $gravity;
    }

    public function knockBack(float $x, float $z, float $force = 0.4, ?float $verticalLimit = 0.4): void {
        $kb = Main::getInstance()->getKB($this->getPosition()->getWorld()->getFolderName());
        if ($kb) {
            $xz = $kb->xz;
            $y = $kb->y;
        } else {
            $xz = $force;
            $y = $force;
        }

        $f = sqrt($x * $x + $z * $z);
        if ($f <= 0) {
            return;
        }
        if (mt_rand() / mt_getrandmax() > $this->knockbackResistanceAttr->getValue()) {
            $f = 1 / $f;

            $motionX = $this->motion->x / 2;
            $motionY = $this->motion->y / 2;
            $motionZ = $this->motion->z / 2;
            $motionX += $x * $f * $xz;
            $motionY += $y;
            $motionZ += $z * $f * $xz;

            $verticalLimit ??= $force;
            if ($motionY > $verticalLimit) {
                $motionY = $verticalLimit;
            }

            $this->setMotion(new Vector3($motionX, $motionY, $motionZ));
        }
    }
}