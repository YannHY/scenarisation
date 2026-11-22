import Foundation

/// Limits actual received bytes, including ignored fields and unterminated lines.
enum BoundedResponse {
    static let limit = 20_000_000

    static func data(for request: URLRequest, session: URLSession, limit: Int = BoundedResponse.limit) async throws -> (Data, URLResponse) {
        let (bytes, response) = try await session.bytes(for: request)
        defer { bytes.task.cancel() }
        guard response.expectedContentLength <= Int64(limit) else { throw tooLarge }
        var data = Data()
        for try await byte in bytes {
            if data.count % 4096 == 0 { try Task.checkCancellation() }
            guard data.count < limit else { throw tooLarge }
            data.append(byte)
        }
        return (data, response)
    }

    static var tooLarge: ScenarioError { .invalid("La réponse du serveur est trop volumineuse.") }

    struct Lines: AsyncSequence {
        typealias Element = String
        let bytes: URLSession.AsyncBytes
        var limit: Int = BoundedResponse.limit
        func makeAsyncIterator() -> Iterator { Iterator(source: bytes.makeAsyncIterator(), limit: limit) }

        struct Iterator: AsyncIteratorProtocol {
            var source: URLSession.AsyncBytes.Iterator
            let limit: Int
            var received = 0
            mutating func next() async throws -> String? {
                var line = Data()
                while let byte = try await source.next() {
                    if received % 4096 == 0 { try Task.checkCancellation() }
                    guard received < limit else { throw BoundedResponse.tooLarge }
                    received += 1
                    if byte == 10 { return String(decoding: line, as: UTF8.self) }
                    if byte != 13 { line.append(byte) }
                }
                return line.isEmpty ? nil : String(decoding: line, as: UTF8.self)
            }
        }
    }
}
