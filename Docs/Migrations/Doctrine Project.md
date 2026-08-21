---
tags:
  - php
  - database
atualizado: 2026-08-20
---
É um ecossistema que contém vários componentes:

- Doctrine DBAL (camada de abstração, mais próxima do SQL)
- Doctrine ORM (construído EM CIMA do DBAL)
- Doctrine Migrations 
- Doctrine Collections 
- outros...

---
## Database Abstractions Layer - DBAL

É uma **camada de abstração** entre o banco de dados e o código. Com ele evitamos escrever SQL na mão. 

Isso nos ajuda, pois cada banco tem sua forma de fazer um SELECT, por exemplo. Com o DBAL, conseguimos escrever de uma forma e ele fica responsável por conversar com o banco de dados.

Dessa forma, consigo mudar de banco de dados sem problemas. Pois sintaxe quem é o responsável é o próprio DBAL. 

#### DBAL vs PDO

PDO é um **data access** e cada database possuí o seu. Ele não traduz o SQL para o seu banco, mas sim conecta o código com o banco de dados.

O DBAL já é diferente, ele atua como um intermediário entre o código e o banco de dados. 
 
---

