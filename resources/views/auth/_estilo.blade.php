<style>
    .auth-card {
        max-width: 460px;
        margin: 0 auto;
        box-sizing: border-box;
    }
    .auth-card .campo { margin-bottom: 14px; }
    .auth-card .campo input[type="text"],
    .auth-card .campo input[type="email"],
    .auth-card .campo input[type="password"] {
        width: 100%;
        box-sizing: border-box;
    }
    .auth-erro {
        margin: 6px 0 0;
        font-size: .85rem;
        color: #ff8a8a;
    }
    .auth-status {
        margin: 0 0 14px;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid var(--linha, #444);
        background: rgba(255, 255, 255, 0.04);
        font-size: .9rem;
    }
    .auth-lembrar {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 4px 0 16px;
        font-size: .9rem;
        color: var(--tinta-fraca, #aaa);
        cursor: pointer;
    }
    .auth-rodape {
        margin: 18px 0 0;
        text-align: center;
        font-size: .9rem;
        color: var(--tinta-fraca, #aaa);
    }
    .auth-rodape a { color: inherit; text-decoration: underline; }
    .auth-card .button { width: 100%; }
</style>