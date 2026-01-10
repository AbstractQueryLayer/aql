# AQL

`AQL` is an abstract query language, similar to `SQL`, 
that can be used as an abstraction layer over databases.

`AQL` is an **abstraction layer** which helps describe business logic closely tied to object storage. 

`AQL` is **not an ORM** solution but a `DSL` — a tool for implementing business logic 
that is closely tied to the way data is stored.

## 🚀 Key Features

### 🧩 AOP - Aspect-Oriented Programming
Allows for programming behavior independently of objects (*entities*), giving more flexibility to how logic is applied.

### 🛠 QueryBuilder
Object-oriented query builder that enables constructing queries incrementally using various syntactic constructs.

### 📨 DTO Objects
Generate DTOs (Data Transfer Objects) directly from entities for clean, structured data handling.

### 📐 Support for CTE Expressions
Allows building CTE (Common Table Expressions) queries at the entity level and correctly processing them.

### 🔒 Scope Control
Provides control over all database queries through a unified interface, ensuring centralized management, like a scope in code.

### ✨ Query Modifiers Support
Enables special transformation of query results based on modifiers. For example, the Tree modifier can turn a tuple into a tree structure.

### 📜 Custom Syntax and Query Logic
Define your own syntax and query execution logic tailored to your application's needs.

### 🔄 PostAction Support
Ensures asynchronous code execution after the data has been applied to the database.

### 🗃️ ORM / DataMapper
Supports common patterns like ORM and DataMapper, helping you structure data efficiently.

## 📦 Storage Futures

### 🔄 Asynchronous Database Connection
Supports asynchronous connections and connection pools, providing high performance and scalability.

### 🔗 Connection Pooling for Asynchronous Applications
Supports connection pooling with databases for asynchronous applications, ensuring efficient management of database connections.

### 🧩 Programmatic Sharding
Enables choosing a database shard based on identifiers or other criteria during queries, offering flexible database partitioning.

### 🔄 ReadWrite Connection Pool
Supports a ReadWrite connection pool, where read requests are directed to Read connections, and write requests are sent to Write connections. The pool automatically accounts for transaction context.

### 🗄️ MultiStorages
Enables interaction with multiple databases within entities, adding flexibility to your data models.

### 🐬 MySQL Support
Provides native support for `MySQL` database integration, syntax, and features.

### 🗃️ Redis Database Support
Provides native support for `Redis` database integration.

### 🐘 PostgreSQL Support
Provides native support for `PostgreSQL` database integration.

## 🔍 Entities futures

### 🏷 Virtual Properties
Supports computed properties that add logic to entities, offering enhanced flexibility.

### 🌳 Entity Inheritance
Allows entities to inherit properties and behaviors from other entities, similar to nodes in a tree structure.

### 🧹 Filters and Virtual Filters
Built-in filtering logic, including virtual filters that can be adapted as needed.

### 🧩 Class Properties
Allows the creation of properties with unique behavior, including control over serialization/deserialization, encryption, and more.

### 🔧 Custom Functions
Define custom functions within `AQL` queries, offering conversions and transformations specific to your needs.

### 🏗️ High-Level Entity Relationship System
A high-level entity relationship system that allows for better descriptions of how entities are interconnected and dependent on each other.

### ⚙️ Customizable Entity Relationships
Allows for the creation of custom, unique strategies to manage relationships between entities, giving more flexibility to the design.

---

## 📦 Monorepo Structure

This is a monorepo containing all AQL packages:

### Core Packages
- **Aspects** - AOP aspects for query execution
- **Dsl** - Domain-Specific Language for building queries
- **Dto** - Data Transfer Object base classes
- **Entity** - Entity definition and management
- **Executor** - Query execution engine
- **Result** - Result handling interfaces
- **Storage** - Storage contracts and interfaces

### Storage Adapters
- **Mysql** - MySQL storage adapter
- **Postgresql** - PostgreSQL storage adapter
- **Sqlite** - SQLite storage adapter
- **Redis** - Redis storage adapter
- **Filesystem** - Filesystem storage adapter

### Driver Abstractions
- **Pdodriver** - PDO driver abstraction
- **Sqldriver** - Abstract SQL driver base class

### Utilities
- **Generator** - DDL and migration generator
- **Postaction** - Post-action handlers
- **Storagepool** - Storage pooling and connection management
- **Telemetry** - Telemetry and monitoring interfaces
- **TestUtils** - Test utilities and base test cases
- **Transaction** - Transaction management

## Installation

```bash
composer install
```

## Testing

```bash
composer test
```

## License

MIT
