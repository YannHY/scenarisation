import Foundation
import CryptoKit

struct SiteSyncAPI: SiteSyncService {
    let baseURL: URL
    private let session: URLSession
    private let credential: @Sendable () async throws -> String
    private let unauthorized: @Sendable (String) async -> Void

    init(baseURL: URL = URL(string: "https://scenarisation.eu/api/v1/")!,
         configuration: URLSessionConfiguration = .ephemeral,
         credential: @escaping @Sendable () async throws -> String,
         unauthorized: @escaping @Sendable (String) async -> Void = { _ in }) throws {
        guard baseURL.scheme == "https", let host = baseURL.host, !host.isEmpty,
              baseURL.user == nil, baseURL.password == nil, baseURL.query == nil, baseURL.fragment == nil else {
            throw SiteSyncError.invalidResponse
        }
        self.baseURL = baseURL
        self.credential = credential
        self.unauthorized = unauthorized
        let configuration = configuration.copy() as! URLSessionConfiguration
        configuration.httpCookieStorage = nil
        configuration.urlCache = nil
        configuration.timeoutIntervalForRequest = 30
        configuration.timeoutIntervalForResource = 60
        session = URLSession(configuration: configuration, delegate: NoRedirect(), delegateQueue: nil)
    }

    static func accountKey(_ value: String) -> String {
        SHA256.hash(data: Data(value.utf8)).map { String(format: "%02x", $0) }.joined()
    }

    func user() async throws -> SiteUser {
        struct Response: Decodable { let success: Bool; let user: SiteUser }
        let response: Response = try await request("me.php")
        guard response.success, response.user.id > 0 else { throw SiteSyncError.invalidResponse }
        return response.user
    }

    func list() async throws -> [SiteDesignSummary] {
        struct Response: Decodable { let success: Bool; let items: [SiteDesignSummary] }
        let response: Response = try await request("list_designs.php")
        guard response.success, response.items.allSatisfy({ $0.id > 0 && $0.revision > 0 }),
              Set(response.items.map(\.id)).count == response.items.count else { throw SiteSyncError.invalidResponse }
        return response.items
    }

    func download(_ id: Int) async throws -> SiteDesign {
        guard id > 0 else { throw SiteSyncError.invalidResponse }
        struct Response: Decodable { let success: Bool; let design: SiteDesign }
        let response: Response = try await request("get_design.php", query: [URLQueryItem(name: "design_id", value: String(id))])
        guard response.success, response.design.id == id, response.design.revision > 0 else {
            throw SiteSyncError.invalidResponse
        }
        return response.design
    }

    func save(_ value: SiteSaveRequest) async throws -> SiteDesignSummary {
        if let id = value.design_id {
            guard id > 0, let revision = value.expected_revision, revision > 0 else { throw SiteSyncError.invalidResponse }
        } else if value.expected_revision != nil { throw SiteSyncError.invalidResponse }
        struct Response: Decodable { let success: Bool; let design: SiteDesignSummary }
        let response: Response = try await request("save_design.php", body: JSONEncoder().encode(value))
        guard response.success, response.design.id > 0, response.design.revision > 0,
              value.design_id == nil || response.design.id == value.design_id,
              value.expected_revision == nil || response.design.revision > value.expected_revision! else {
            throw SiteSyncError.invalidResponse
        }
        return response.design
    }

    func delete(_ id: Int, expectedRevision: Int) async throws {
        guard id > 0, expectedRevision > 0 else { throw SiteSyncError.invalidResponse }
        struct Body: Encodable { let design_id: Int; let expected_revision: Int }
        struct Deleted: Decodable { let id: Int }
        struct Response: Decodable { let success: Bool; let deleted: Deleted }
        let response: Response = try await request("delete_design.php",
            body: JSONEncoder().encode(Body(design_id: id, expected_revision: expectedRevision)))
        guard response.success, response.deleted.id == id else { throw SiteSyncError.invalidResponse }
    }

    private func request<Response: Decodable>(_ path: String, query: [URLQueryItem] = [], body: Data? = nil) async throws -> Response {
        var components = URLComponents(url: baseURL.appendingPathComponent(path), resolvingAgainstBaseURL: false)!
        if !query.isEmpty { components.queryItems = query }
        var request = URLRequest(url: components.url!)
        let token = try await credential()
        guard !token.isEmpty, !token.contains("\r"), !token.contains("\n") else { throw SiteSyncError.unauthorized }
        request.httpMethod = body == nil ? "GET" : "POST"
        request.setValue("Bearer " + token, forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.cachePolicy = .reloadIgnoringLocalCacheData
        request.httpBody = body
        if body != nil { request.setValue("application/json", forHTTPHeaderField: "Content-Type") }
        let (data, raw) = try await session.data(for: request)
        try Task.checkCancellation()
        guard let response = raw as? HTTPURLResponse else { throw SiteSyncError.invalidResponse }
        switch response.statusCode {
        case 401:
            await unauthorized(token)
            throw SiteSyncError.unauthorized
        case 403: throw SiteSyncError.forbidden
        case 404: throw SiteSyncError.missing
        case 409: throw SiteSyncError.conflict
        case 200...299: break
        default: throw SiteSyncError.invalidResponse
        }
        guard response.mimeType == "application/json", data.count <= 20_000_000 else { throw SiteSyncError.invalidResponse }
        do { return try JSONDecoder().decode(Response.self, from: data) }
        catch { throw SiteSyncError.invalidResponse }
    }
}
