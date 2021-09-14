<?php
namespace Edisom\Core;

abstract class Api extends Controller
{	
	protected function __construct(array $query = null)
	{	
		header('Content-Type: application/json');
		parent::__construct($query);
	}
}