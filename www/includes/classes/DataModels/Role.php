<?php

namespace ISLE\DataModels;

class Role extends Enum
{
    public const DOES_NOT_EXIST = 0;
    public const DISABLED = 1;
    public const VIEWER = 2;
    public const USER = 4;
    public const CONTRIBUTOR = 8;
    public const ADMINISTRATOR = 16;
}
