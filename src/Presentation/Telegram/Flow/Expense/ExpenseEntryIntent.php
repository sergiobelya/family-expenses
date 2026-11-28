<?php

namespace App\Presentation\Telegram\Flow\Expense;

enum ExpenseEntryIntent
{
    case ADD;
    case CONTINUE;
}