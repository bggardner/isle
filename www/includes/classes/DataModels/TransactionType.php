<?php

namespace ISLE\DataModels;

class TransactionType extends Enum
{
    public const CHECK_OUT = 1;
    public const CHECK_IN = 2;
    public const RESTRICT = 3;
    public const UNRESTRICT = 4;
}
