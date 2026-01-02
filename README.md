# Controle DO


## Test De Qualite

On verifie la fonctionnalitem maintenabilite, securite et performance du code fournie. 

1. Analyse statique (lecture sans execution)
- Linting : 
    app/index.php:\
        Invalid HTML Nesting: le tag h2 est ferme avant que le tag small est ferme.\
        Le tag cite n'est pas ferme correctement  ( cite a la place de /cite)\

    app/validation.php\
        !isset($_POST["title"]) doit etre !isset($_POST["authour"]) en 2eme elseif\

- Security :


