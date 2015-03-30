<?php


/**
 * Advanced Routes are made to generate flexible resource routes, like Rails does
 * 
 * @package   Advanced_Routes
 * @author    Alexei Shabalin <mail@alshabalin.com>
 */
class Advanced_Route extends Kohana_Route {


  public $params = [];

  public function __construct($uri = NULL, $regex = NULL)
  {
    parent::__construct($uri, $regex);

    if (preg_match_all('#<(\w+)>#', $uri, $matches))
    {
      foreach ($matches[1] as $match)
      {
        $this->params[] = $match;
      }
    }
  }

  /**
   * Rails-style defaults:
   * Route::set('name', 'articles')->to('Articles#index');
   * Route::root()->to('Article#index');
   */
  public function to($defaults)
  {
    if (is_string($defaults) && preg_match('~(?:(?<directory>\w+)/)?(?<controller>\w+)(?:#(?<action>\w+))?~', $defaults, $matches))
    {
      $defaults = [
        'controller' => $matches['controller'],
        'action' => $matches['action'] ? $matches['action'] : 'index',
      ];
      if ($matches['directory'])
      {
        $defaults['directory'] = $matches['directory'];
      }
    }
    return parent::defaults((array)$defaults);
  }

  public function method($method)
  {
    return $this->filter(function(Route $route, $params, Request $request) use ($method)
    {
      if ($method !== 'ALL' && $method !== 'ANY' && ! in_array($request->method(), (array)$method))
      {
        // throw new HTTP_Exception_405('Method not allowed');
        return FALSE;
      }
    });
  }

  public function domain($domain)
  {
    return $this->filter(function(Route $route, $params, Request $request) use ($domain)
    {
      if ( ! in_array($_SERVER['HTTP_HOST'], (array)$domain))
      {
        return FALSE;
      }
    });
    return $this;
  }

  protected static $prefixes = [];

  protected static $controller_stack = [];
  protected static $directory_stack = [];
  protected static $regex_stack = [];

  public static function resources($resource, Closure $closure = NULL)
  {
    $new   = 'new';
    $edit  = 'edit';
    $param = 'id';
    $regex = [];

    $directory = NULL;

    $_index = $_show = $_edit = $_new = $_create = $_update = $_destroy = TRUE;

    if (is_array($resource))
    {
      $options  = $resource;
      $resource = $options[0];

      empty($options['directory'])   || $directory = ucfirst($options['directory']);
      empty($options['controller'])  || $controller = ucfirst($options['controller']);
      empty($options['as'])          || $as = $options['as'];
      empty($options['new'])         || $new = $options['new'];
      empty($options['edit'])        || $edit = $options['edit'];
      empty($options['param'])       || $param = $options['param'];
      empty($options['regex'])       || $regex = $options['regex'];
      empty($options['path'])        || $path = ltrim($options['path'], '/');

      if ( ! empty($options['only']))
      {
        $_index = $_show = $_edit = $_new = $_create = $_update = $_destroy = FALSE;
        foreach ($options['only'] as $_only)
        {
          ${'_' . $_only} = TRUE;
        }
      }
      else if ( ! empty($options['except']))
      {
        foreach ($options['only'] as $_only)
        {
          ${'_' . $_only} = FALSE;
        }
      }
    }


    empty($controller)  && $controller = ucfirst($resource);
    empty($as)          && $as = $resource;
    empty($path)        && $path = $resource . '/';

    $one = Inflector::singular($as);
    $as = Inflector::plural($one);

    if ($as === $one)
    {
      $as .= '_index';
    }

    $f = '(.<format>)';

    $prefix_path   = '';
    $prefix_name   = '';

    if (count(static::$prefixes))
    {
      foreach (static::$prefixes as $i => list( $_name, $_path, $_directory ) )
      {
        $prefix_path   .= $_path[0];
        $prefix_name   .= $_name;
        $directory     .= $_directory;
      }
    }

    $_new     && Route::set('new_' . $prefix_name . $one,      $prefix_path . $path . 'new' . $f)      ->method('GET')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => 'new']);

    if ($closure !== NULL)
    {
      array_unshift(static::$controller_stack, $controller);
      array_unshift(static::$directory_stack, $directory);
      array_unshift(static::$regex_stack, $regex);
      static::$prefixes[] = [ $one . '_' , [ $path . '<' . $one . '_' . $param . '>/' , $path . '<' . $param . '>/' ], '' ];
      $closure();
      array_pop(static::$prefixes);
      array_shift(static::$controller_stack);
      array_shift(static::$directory_stack);
      array_shift(static::$regex_stack);
    }

    $_edit    && Route::set('edit_' . $prefix_name . $one,     $prefix_path . $path . '<' . $param . '>/edit' . $f, $regex)->method('GET')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => 'edit']);
    $_show    && Route::set($prefix_name . $one,               $prefix_path . $path . '<' . $param . '>' . $f,      $regex)->method('GET')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => 'show']);
    $_create  && Route::set('create_' . $prefix_name . $one,   rtrim($prefix_path . $path, '/') . $f,               $regex)->method('POST')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => 'create']);
    $_update  && Route::set('update_' . $prefix_name . $one,   $prefix_path . $path . '<' . $param . '>' . $f,      $regex)->method(['PUT', 'PATCH'])->defaults(['directory' => $directory, 'controller' => $controller, 'action' => 'update']);
    $_destroy && Route::set('destroy_' . $prefix_name . $one,  $prefix_path . $path . '<' . $param . '>' . $f,      $regex)->method('DELETE')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => 'destroy']);
    $_index   && Route::set($prefix_name . $as,                rtrim($prefix_path . $path, '/') . $f,               $regex)->method('GET')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => 'index']);
  }


  public static function resource($resource, Closure $closure = NULL)
  {
    $new   = 'new';
    $edit  = 'edit';
    $param = 'id';
    $regex = [];

    $directory = NULL;

    $_show = $_edit = $_new = $_create = $_update = $_destroy = TRUE;

    if (is_array($resource))
    {
      $options = $resource;
      $resource = $options[0];

      empty($options['controller'])  || $controller = ucfirst($options['controller']);
      empty($options['directory'])   || $directory = ucfirst($options['directory']);
      empty($options['as'])          || $as = $options['as'];
      empty($options['new'])         || $new = $options['new'];
      empty($options['edit'])        || $edit = $options['edit'];
      empty($options['param'])       || $param = $options['param'];
      empty($options['regex'])       || $regex = $options['regex'];
      empty($options['path'])        || $path = ltrim($options['path'], '/');
      if ( ! empty($options['only']))
      {
        $_show = $_edit = $_new = $_create = $_update = $_destroy = FALSE;
        foreach ($options['only'] as $_only)
        {
          ${'_' . $_only} = TRUE;
        }
      }
      else if ( ! empty($options['except']))
      {
        foreach ($options['only'] as $_only)
        {
          ${'_' . $_only} = FALSE;
        }
      }
    }


    empty($controller)  && $controller = ucfirst($resource);
    empty($as)          && $as = $resource;
    empty($path)        && $path = $resource . '/';

    $f = '(.<format>)';

    $prefix_path = '';
    $prefix_name = '';

    if (count(static::$prefixes))
    {
      foreach (static::$prefixes as list( $_name, $_path, $_directory ) )
      {
        $prefix_path .= $_path[0];
        $prefix_name .= $_name;
        $directory   .= $_directory;
      }
    }

    $_edit    && Route::set('edit_' . $prefix_name . $as,     $prefix_path . $path . 'edit' . $f, $regex)->method('GET')->defaults(['controller' => $controller, 'action' => 'edit']);
    $_new     && Route::set('new_' . $prefix_name . $as,      $prefix_path . $path . 'new' . $f,  $regex)->method('GET')->defaults(['controller' => $controller, 'action' => 'new']);

    if ($closure !== NULL)
    {
      array_unshift(static::$controller_stack, $controller);
      array_unshift(static::$directory_stack, '');
      array_unshift(static::$regex_stack, $regex);
      static::$prefixes[] = [ $as . '_' , $path, NULL ];
      $closure();
      array_pop(static::$prefixes);
      array_shift(static::$controller_stack);
      array_shift(static::$directory_stack);
      array_shift(static::$regex_stack);
    }

    $_create  && Route::set('create_' . $prefix_name . $as,   rtrim($prefix_path . $path, '/') . $f, $regex)->method('POST')->defaults(['controller' => $controller, 'action' => 'create']);
    $_update  && Route::set('update_' . $prefix_name . $as,   rtrim($prefix_path . $path, '/') . $f, $regex)->method(['PUT', 'PATCH'])->defaults(['controller' => $controller, 'action' => 'update']);
    $_destroy && Route::set('destroy_' . $prefix_name . $as,  rtrim($prefix_path . $path, '/') . $f, $regex)->method('DELETE')->defaults(['controller' => $controller, 'action' => 'destroy']);
    $_show    && Route::set($prefix_name . $as,               rtrim($prefix_path . $path, '/') . $f, $regex)->method('GET')->defaults(['controller' => $controller, 'action' => 'show']);
  }


  public static function directory($directory, Closure $closure = NULL)
  {
    if ($closure !== NULL)
    {
      static::$prefixes[] = [ $directory . '_' , [ $directory . '/' , $directory . '/' ], ucfirst($directory) . '/' ];
      array_unshift(static::$directory_stack, $directory);
      $closure();
      array_pop(static::$prefixes);
      array_shift(static::$directory_stack);
    }
  }

  public static function scope($scope, Closure $closure = NULL)
  {
    if ($closure !== NULL)
    {
      static::$prefixes[] = [ '' , [ $scope . '/', $scope . '/' ], NULL ];
      $closure();
      array_pop(static::$prefixes);
    }
  }


  public static function member($action, array $options = NULL)
  {
    if ( ! count(static::$controller_stack))
    {
      return;
    }

    $f = '(.<format>)';

    $_new = Arr::get($options, 'on') === 'new';

    $prefix_path     = '';
    $new_prefix_path = '';
    $prefix_name     = '';

    if (count(static::$prefixes))
    {
      foreach (static::$prefixes as $i => list( $_name, $_path ) )
      {
        $prefix_path     .= $_path[($i === count(static::$prefixes) - 1 ? 1 : 0)];
        $_new && $new_prefix_path .= ($i === count(static::$prefixes) - 1) ? preg_replace('#/<\w+>/?$#', '/new/', $_path[0]) : $_path[0];
        $prefix_name     .= $_name;
      }
    }

    $controller = static::$controller_stack[0];
    $directory = static::$directory_stack[0];
    $regex = static::$regex_stack[0];

    Route::set(trim($action . '_' . $prefix_name, '_'), rtrim($prefix_path, '/') . '/' . $action . $f, $regex)->method('GET')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => $action]);

    $_new && Route::set(trim($action . '_new_' . $prefix_name, '_'), rtrim($new_prefix_path, '/') . '/' . $action . $f, $regex)->method('GET')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => $action]);
  }


  public static function collection($action)
  {
    if ( ! count(static::$controller_stack))
    {
      return;
    }

    $f = '(.<format>)';

    $prefix_path = '';
    $prefix_name = '';

    if (count(static::$prefixes))
    {
      foreach (static::$prefixes as list( $_name, $_path ) )
      {
        $prefix_path .= $_path[0];
        $prefix_name .= $_name;
      }
    }

    $controller = static::$controller_stack[0];
    $directory = static::$directory_stack[0];
    $regex = static::$regex_stack[0];

    $prefix_path = preg_replace('#<\w+>/$#', '', $prefix_path);

    Route::set(Inflector::plural(trim($action . '_' . $prefix_name, '_')), rtrim($prefix_path, '/') . '/' . $action . $f, $regex)->method('GET')->defaults(['directory' => $directory, 'controller' => $controller, 'action' => $action]);
  }




  public static function url_for($options, $type = 'url')
  {
    if (is_string($options))
    {
      return $options;
    }
    else if ($options instanceof ORM)
    {
      $model = $options;

      $route = [
        $model->loaded() ? $model->object_name() : $model->object_plural(),
        $type,
      ];

      $route = implode('_', $route);

      return Route::__callStatic($route, [$options]); // forward_static_call_array(['Route', $route], [$options]);
    }
    else if (is_array($options))
    {
      // check if we got a collection of objects or key-value array
      if (isset($options[0]))
      {
        $route = [];

        foreach ($options as $model)
        {
          if ($model instanceof ORM)
          {
            $route[] = $model->loaded() ? $model->object_name() : $model->object_plural();
          }
          else
          {
            $route[] = $model;
          }
        }

        $route[] = $type;

        $route = implode('_', $route);

        return Route::__callStatic($route, $options); // forward_static_call_array(['Route', $route], [$options]);
      }
      else
      {
        Route::default_path($options);
      }
    }
  }

  public static function root()
  {
    return Route::set('root', '');
  }



  public static function explain()
  {
    $routes = [];
    foreach (Route::all() as $name => $route)
    {
      $routes[] = str_pad($name, 40) . '| ' 
              . str_pad((!empty($route->_defaults['directory']) ? $route->_defaults['directory'] : '') . $route->_defaults['controller'] . '#' . $route->_defaults['action'], 30) . '| ' 
              . $route->_uri
              ;
    }
    return implode("\n", $routes);
  }





  public static function __callStatic($method_name, array $args = NULL)
  {
    if (preg_match('#^(?<route>.+)(?:_(?<type>path|url))$#', $method_name, $matches))
    {
      $route_name = $matches['route'];
      $type       = $matches['type'];
      $route      = Route::get($route_name);
      $params     = [];
      $query      = [];

      if (is_array($args))
      {
        foreach ($args as $arg)
        {
          if (is_array($arg))
          {
            foreach ($route->params as $param)
            {
              if (isset($arg[$param]))
              {
                $params[$param] = Arr::get($arg, $param, NULL);
                unset($arg[$param]);
              }
            }
            foreach ($arg as $param => $value)
            {
              $query[$param] = $value;
            }
          }
          else if ($arg instanceof ORM)
          {
            $name_prefix = $arg->object_name() . '_';

            foreach ($arg->table_columns() as $param => $definition)
            {
              if (in_array($param, $route->params))
              {
                $params[$param] = $arg->{$param};
              }
              else if (in_array($name_prefix . $param, $route->params))
              {
                $params[$name_prefix . $param] = $arg->{$param};
              }
            }
          }
          else
          {
            $params[$route->params[0]] = (string)$arg;
          }
        }
      }

      $url = $route->uri($params);

      return URL::site($url, $type === 'url') . (count($query) ? '?' . http_build_query($query) : '');
    }
  }








}
