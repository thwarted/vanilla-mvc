<html>
    <head>
        <title>{$exception->getMessage()}</title>
    </head>
    <body>
        <h1>{$exception->getCode()} Unauthorized</h1>
        {$exception->getMessage()}
        <hr/>
        <em>{$smarty.server.SERVER_SOFTWARE}</em>
    </body>
</html>
{"this page is customizable"|@d}
