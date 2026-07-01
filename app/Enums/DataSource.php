<?php

namespace App\Enums;

enum DataSource: string
{
    case FootballData = 'football_data';
    case ApiFootball = 'api_football';
}
